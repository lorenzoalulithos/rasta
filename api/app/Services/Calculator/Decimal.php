<?php declare(strict_types=1);

namespace App\Services\Calculator;

use InvalidArgumentException;
use RoundingMode;

/**
 * Decimal arithmetic on numeric strings with bcmath: never floats in money (stack.md).
 */
final class Decimal
{
    /**
     * Scale of intermediate results, rounded only at the end (domain.md §7.1).
     *
     * @var int
     */
    public const WORKING_SCALE = 10;

    /**
     * Decimals of money: DECIMAL(10,2).
     *
     * @var int
     */
    public const MONEY = 2;

    /**
     * Decimals of quantities and packages: DECIMAL(10,3).
     *
     * @var int
     */
    public const QUANTITY = 3;

    /**
     * @param float|int|string $value
     *
     * @throws InvalidArgumentException
     *
     * @return numeric-string
     */
    public static function of(float|int|string $value): string
    {
        $value = is_float($value) ? sprintf('%.' . self::WORKING_SCALE . 'F', $value) : (string) $value;

        if (!is_numeric($value)) {
            throw new InvalidArgumentException("Not a number: {$value}");
        }

        return $value;
    }

    /**
     * @param float|int|string|null $value
     *
     * @return numeric-string|null
     */
    public static function nullable(float|int|string|null $value): ?string
    {
        return null === $value ? null : self::of($value);
    }

    /**
     * Half away from zero, e.g. 143.065 → 143.07.
     *
     * @param numeric-string $value
     * @param int            $decimals
     *
     * @return numeric-string
     */
    public static function round(string $value, int $decimals): string
    {
        return bcadd(bcround($value, $decimals, RoundingMode::HalfAwayFromZero), '0', $decimals);
    }

    /**
     * Smallest integer not lower than the value, written with the given decimals.
     *
     * @param numeric-string $value
     * @param int            $decimals
     *
     * @return numeric-string
     */
    public static function ceil(string $value, int $decimals): string
    {
        return bcadd(self::of(bcceil($value)), '0', $decimals);
    }

    /**
     * @param numeric-string $value
     *
     * @return bool
     */
    public static function isPositive(string $value): bool
    {
        return 1 === bccomp($value, '0', self::WORKING_SCALE);
    }
}
