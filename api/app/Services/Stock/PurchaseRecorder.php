<?php declare(strict_types=1);

namespace App\Services\Stock;

use App\Enums\StockMovementType;
use App\Models\Database\v1\Material;
use App\Models\Database\v1\Purchase;
use App\Models\Database\v1\StockMovement;
use App\Services\Calculator\Decimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Records a purchase (domain.md §3.13, flows.md §3.3): its lines, a `purchase` movement per line
 * that loads the closed packages, and the new list price of the materials the user confirmed.
 */
final class PurchaseRecorder
{
    /**
     * @param StockService $stock
     */
    public function __construct(private readonly StockService $stock)
    {
    }

    /**
     * @param array{supplier: string|null, purchased_at: string, paid: bool, notes: string|null} $attributes
     * @param list<array{material_id: string, packages: int, unit_cost: string}>                 $lines
     * @param list<string>                                                                       $updatePrices materials whose cost_price becomes the unit cost of their line
     *
     * @return Purchase
     */
    public function record(array $attributes, array $lines, array $updatePrices = []): Purchase
    {
        return DB::transaction(function () use ($attributes, $lines, $updatePrices): Purchase {
            $purchase = Purchase::query()->create([...$attributes, 'total' => self::total($lines)]);
            $materials = Material::query()->whereKey(array_column($lines, 'material_id'))->get()->keyBy('id');

            foreach ($lines as $line) {
                /** @var Material $material */
                $material = $materials->get($line['material_id']);

                $purchase->lines()->create([...$line, 'tenant_id' => $purchase->tenant_id]);
                $this->stock->lock($material)->increment('closed_packages', $line['packages']);

                StockMovement::query()->create([
                    'tenant_id' => $purchase->tenant_id,
                    'material_id' => $material->id,
                    'type' => StockMovementType::Purchase,
                    'closed_delta' => $line['packages'],
                    'opened_delta' => 0,
                    'purchase_id' => $purchase->id,
                    'notes' => $purchase->supplier,
                    'occurred_at' => Carbon::now(),
                ]);

                if (in_array($material->id, $updatePrices, true)) {
                    $material->update(['cost_price' => $line['unit_cost']]);
                }
            }

            return $purchase;
        });
    }

    /**
     * Σ packages × unit_cost.
     *
     * @param list<array{material_id: string, packages: int, unit_cost: string}> $lines
     *
     * @return string
     */
    private static function total(array $lines): string
    {
        return array_reduce(
            $lines,
            static fn (string $total, array $line): string => bcadd($total, bcmul((string) $line['packages'], Decimal::of($line['unit_cost']), Decimal::MONEY), Decimal::MONEY),
            '0.00',
        );
    }
}
