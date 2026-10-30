<?php declare(strict_types=1);

namespace App\Services\Stock;

use App\Enums\QuoteStatus;
use App\Enums\StockMovementType;
use App\Enums\StockStatus;
use App\Models\Database\v1\Material;
use App\Models\Database\v1\QuoteLineMaterial;
use App\Models\Database\v1\Stock;
use App\Models\Database\v1\StockMovement;
use App\Services\Calculator\Decimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Stock, domain.md §3.11–3.12. Figures are derived on read, never stored:
 *
 *   on_hand   = closed + opened × opened_package_ratio
 *   committed = Σ required_packages of selected line materials of accepted quotes
 *   available = on_hand − committed
 *   to_buy    = max(0, min_stock − available), rounded up
 *
 * Every change of closed/opened packages goes through a StockMovement.
 */
final class StockService
{
    /**
     * Decimals of package figures (domain.md §7.1).
     *
     * @var int
     */
    private const SCALE = 3;

    /**
     * @param Stock|null  $stock     null reads as an empty stock
     * @param string      $ratio     tenant opened_package_ratio, e.g. "0.50"
     * @param string|null $committed sum of committed required_packages, null when nothing is committed
     *
     * @return array{closed_packages: int, opened_packages: int, on_hand: float, committed: float, available: float}
     */
    public function figures(?Stock $stock, string $ratio, ?string $committed): array
    {
        $closed = $stock->closed_packages ?? 0;
        $opened = $stock->opened_packages ?? 0;
        $ratio = is_numeric($ratio) ? $ratio : '0';
        $committed = is_numeric($committed) ? $committed : '0';

        $onHand = bcadd((string) $closed, bcmul((string) $opened, $ratio, self::SCALE), self::SCALE);

        return [
            'closed_packages' => $closed,
            'opened_packages' => $opened,
            'on_hand' => (float) $onHand,
            'committed' => (float) $committed,
            'available' => (float) bcsub($onHand, $committed, self::SCALE),
        ];
    }

    /**
     * @param float        $available
     * @param float|string $minStock
     *
     * @return StockStatus
     */
    public function status(float $available, float|string $minStock): StockStatus
    {
        $available = Decimal::of($available);

        return match (true) {
            bccomp($available, '0', self::SCALE) < 0 => StockStatus::Negative,
            bccomp($available, Decimal::of($minStock), self::SCALE) < 0 => StockStatus::Low,
            default => StockStatus::Ok,
        };
    }

    /**
     * Whole packages to buy to get back to the minimum stock.
     *
     * @param float        $available
     * @param float|string $minStock
     *
     * @return int
     */
    public function toBuy(float $available, float|string $minStock): int
    {
        $missing = bcsub(Decimal::of($minStock), Decimal::of($available), self::SCALE);

        return Decimal::isPositive($missing) ? (int) Decimal::ceil($missing, 0) : 0;
    }

    /**
     * Restricts a material query to the ones whose available stock is below the minimum (the "da comprare" list).
     * It joins `stocks`: the query must select `materials.*` explicitly.
     *
     * @param Builder<Material> $query
     * @param string            $ratio tenant opened_package_ratio
     *
     * @return void
     */
    public function whereToBuy(Builder $query, string $ratio): void
    {
        // available < min_stock, with the same committed sum as Material::commitments()
        $query->leftJoin('stocks', 'stocks.material_id', '=', 'materials.id')
              ->whereRaw(
                  '(COALESCE(stocks.closed_packages, 0) + COALESCE(stocks.opened_packages, 0) * ?'
                  . ' - (SELECT COALESCE(SUM(qlm.required_packages), 0) FROM quote_line_materials qlm'
                  . ' JOIN quote_lines ql ON ql.id = qlm.quote_line_id'
                  . ' JOIN quotes q ON q.id = ql.quote_id'
                  . ' WHERE qlm.material_id = materials.id AND qlm.selected = 1 AND q.status = ?)'
                  . ') < materials.min_stock',
                  [is_numeric($ratio) ? $ratio : '0', QuoteStatus::Accepted->value],
              );
    }

    /**
     * Accepted quotes that commit the material, with their packages (flows.md §3.1–3.2).
     *
     * @param Material $material
     *
     * @return Collection<int, array{quote: array{id: string|null, number: int|null, year: int|null, customer: string|null, site: string|null}, packages: float}>
     */
    public function commitments(Material $material): Collection
    {
        return $material->commitments()
                        ->with('line.quote.site.customer')
                        ->get()
                        ->groupBy(static fn (QuoteLineMaterial $item): string => (string) $item->line?->quote_id)
                        ->map(static function (Collection $items): array {
                            /** @var QuoteLineMaterial $first */
                            $first = $items->first();
                            $quote = $first->line?->quote;

                            return [
                                'quote' => [
                                    'id' => $quote?->id,
                                    'number' => $quote?->number,
                                    'year' => $quote?->year,
                                    'customer' => $quote?->site?->customer?->name,
                                    'site' => $quote?->site?->name,
                                ],
                                'packages' => (float) $items->sum(static fn (QuoteLineMaterial $item): float => (float) $item->required_packages),
                            ];
                        })
                        ->values();
    }

    /**
     * "Rettifica": sets the absolute closed/opened packages and records the delta (flows.md §3.2).
     *
     * @param Material    $material
     * @param int         $closed
     * @param int         $opened
     * @param string|null $notes
     *
     * @return StockMovement|null the movement, null when nothing changed
     */
    public function adjust(Material $material, int $closed, int $opened, ?string $notes = null): ?StockMovement
    {
        return DB::transaction(static function () use ($material, $closed, $opened, $notes): ?StockMovement {
            $stock = Stock::query()->whereKey($material->id)->lockForUpdate()->first()
                ?? Stock::query()->create(['material_id' => $material->id, 'tenant_id' => $material->tenant_id]);

            $closedDelta = $closed - $stock->closed_packages;
            $openedDelta = $opened - $stock->opened_packages;

            if (0 === $closedDelta && 0 === $openedDelta) {
                return null;
            }

            $stock->update(['closed_packages' => $closed, 'opened_packages' => $opened]);

            return StockMovement::query()->create([
                'tenant_id' => $material->tenant_id,
                'material_id' => $material->id,
                'type' => StockMovementType::Adjustment,
                'closed_delta' => $closedDelta,
                'opened_delta' => $openedDelta,
                'notes' => $notes,
                'occurred_at' => Carbon::now(),
            ]);
        });
    }
}
