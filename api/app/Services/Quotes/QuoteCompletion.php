<?php declare(strict_types=1);

namespace App\Services\Quotes;

use App\Enums\QuoteStatus;
use App\Enums\StockMovementType;
use App\Models\Database\v1\Material;
use App\Models\Database\v1\Quote;
use App\Models\Database\v1\QuoteLineMaterial;
use App\Models\Database\v1\StockMovement;
use App\Services\Calculator\Decimal;
use App\Services\Stock\StockService;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Chiusura cantiere, domain.md §5 rule 10 and flows.md §6: the estimated consumption as a proposal,
 * then the confirmed one as `consumption` movements and the quote `completed`.
 *
 * "Used X closed + Y opened": X + Y packages leave the stock, Y come back started (closed −(X + Y), opened +Y).
 */
final class QuoteCompletion
{
    /**
     * @param StockService $stock
     */
    public function __construct(private readonly StockService $stock)
    {
    }

    /**
     * One proposal per selected material, summed over the lines.
     *
     * @param Quote $quote
     *
     * @return list<array{material: array{id: string, name: string, package_label: string}, estimated_packages: float, suggested_closed: int, suggested_opened: int, stock: array{closed_packages: int, opened_packages: int}}>
     */
    public function proposal(Quote $quote): array
    {
        self::assertAccepted($quote);

        $items = QuoteLineMaterial::query()
                                  ->where('selected', true)
                                  ->whereHas('line', static fn ($line) => $line->where('quote_id', $quote->id))
                                  ->with('material.stock')
                                  ->get()
                                  ->groupBy('material_id');

        $proposal = [];

        foreach ($items as $group) {
            /** @var QuoteLineMaterial $first */
            $first = $group->first();
            /** @var Material $material */
            $material = $first->material;
            $estimated = $group->reduce(static fn (string $sum, QuoteLineMaterial $item): string => bcadd($sum, Decimal::of($item->required_packages), Decimal::QUANTITY), '0');
            // bcadd with scale 0 truncates: the whole packages of a positive estimate
            $whole = (int) bcadd($estimated, '0', 0);
            $started = 1 === bccomp($estimated, (string) $whole, Decimal::QUANTITY) ? 1 : 0;

            $proposal[] = [
                'material' => ['id' => $material->id, 'name' => $material->name, 'package_label' => $material->package_label],
                'estimated_packages' => (float) $estimated,
                'suggested_closed' => $whole,
                'suggested_opened' => $started,
                'stock' => [
                    'closed_packages' => $material->stock->closed_packages ?? 0,
                    'opened_packages' => $material->stock->opened_packages ?? 0,
                ],
            ];
        }

        usort($proposal, static fn (array $one, array $other): int => strcmp($one['material']['name'], $other['material']['name']));

        return $proposal;
    }

    /**
     * @param Quote                                                       $quote
     * @param list<array{material_id: string, closed: int, opened: int}> $consumptions
     *
     * @return list<array{material_id: string, name: string, closed_delta: int}> the implicit adjustments
     */
    public function complete(Quote $quote, array $consumptions): array
    {
        self::assertAccepted($quote);

        return DB::transaction(function () use ($quote, $consumptions): array {
            $materials = Material::query()->whereKey(array_column($consumptions, 'material_id'))->get()->keyBy('id');
            $adjustments = [];

            foreach ($consumptions as $consumption) {
                /** @var Material $material */
                $material = $materials->get($consumption['material_id']);
                $adjustment = $this->consume($quote, $material, $consumption['closed'], $consumption['opened']);

                if (null !== $adjustment) {
                    $adjustments[] = ['material_id' => $material->id, 'name' => $material->name, 'closed_delta' => $adjustment];
                }
            }

            $quote->forceFill(['status' => QuoteStatus::Completed, 'completed_at' => Carbon::now()])->save();

            return $adjustments;
        });
    }

    /**
     * Records the consumption of one material; returns the closed packages added by the implicit adjustment, if any.
     *
     * @param Quote    $quote
     * @param Material $material
     * @param int      $closed   packages finished
     * @param int      $opened   packages started, back in stock as opened
     *
     * @return int|null
     */
    private function consume(Quote $quote, Material $material, int $closed, int $opened): ?int
    {
        $taken = $closed + $opened;

        if (0 === $taken) {
            return null;
        }

        $stock = $this->stock->lock($material);
        $missing = max(0, $taken - $stock->closed_packages);
        $now = Carbon::now();

        $this->movement($quote, $material, StockMovementType::Consumption, -$taken, $opened, null, $now);

        if ($missing > 0) {
            $this->movement($quote, $material, StockMovementType::Adjustment, $missing, 0, "Rettifica automatica alla chiusura del preventivo {$quote->number}/{$quote->year}", $now);
        }

        $stock->update([
            'closed_packages' => $stock->closed_packages - $taken + $missing,
            'opened_packages' => $stock->opened_packages + $opened,
        ]);

        return $missing > 0 ? $missing : null;
    }

    /**
     * @param Quote             $quote
     * @param Material          $material
     * @param StockMovementType $type
     * @param int               $closedDelta
     * @param int               $openedDelta
     * @param string|null       $notes
     * @param Carbon            $occurredAt
     *
     * @return void
     */
    private function movement(Quote $quote, Material $material, StockMovementType $type, int $closedDelta, int $openedDelta, ?string $notes, Carbon $occurredAt): void
    {
        StockMovement::query()->create([
            'tenant_id' => $quote->tenant_id,
            'material_id' => $material->id,
            'type' => $type,
            'closed_delta' => $closedDelta,
            'opened_delta' => $openedDelta,
            'quote_id' => $quote->id,
            'notes' => $notes,
            'occurred_at' => $occurredAt,
        ]);
    }

    /**
     * @param Quote $quote
     *
     * @return void
     */
    private static function assertAccepted(Quote $quote): void
    {
        abort_unless(
            QuoteStatus::Accepted === $quote->status,
            Response::HTTP_CONFLICT,
            "Il preventivo è {$quote->status->label()}: si chiude solo un cantiere accettato.",
        );
    }
}
