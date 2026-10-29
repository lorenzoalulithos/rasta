<?php declare(strict_types=1);

namespace App\Services\Stock;

use App\Models\Database\v1\Stock;

/**
 * Derived stock figures, domain.md §3.11. Computed on read, never stored.
 *
 * In packages, with bcmath: on_hand = closed + opened × ratio, available = on_hand − committed.
 * The rest of the stock logic (to_buy, status, adjustments) comes with A3.1.
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
}
