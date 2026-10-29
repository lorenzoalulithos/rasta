<?php declare(strict_types=1);

namespace Database\Constants;

/**
 * Class MigrationConstants.
 */
final class MigrationConstants
{
    /**
     * Maximum length for `additional_info` field.
     *
     * @var int
     */
    public const ADDITIONAL_INFO_MAX_LENGTH = 500;

    /**
     * Length of hex colors, e.g. `#A10CB4`.
     *
     * @var int
     */
    public const CAP_STRING_LENGTH = 5;

    /**
     * Length of long strings in database, e.g. Long description.
     *
     * @var int
     */
    public const LONG_STRING_MAX_LENGTH = 500;

    /**
     * Length of medium strings in database, e.g. little description.
     *
     * @var int
     */
    public const MEDIUM_STRING_MAX_LENGTH = 150;

    /**
     * Length of short strings in database, e.g. titles.
     *
     * @var int
     */
    public const SHORT_STRING_MAX_LENGTH = 50;

    /**
     * Length of super long strings in database, e.g. Tooltip text.
     *
     * @var int
     */
    public const SUPER_LONG_STRING_MAX_LENGTH = 3000;

    /**
     * Total digits of money and quantity columns: DECIMAL(10, x).
     *
     * @var int
     */
    public const DECIMAL_PRECISION = 10;

    /**
     * Decimals of money columns: DECIMAL(10,2), never float (domain.md).
     *
     * @var int
     */
    public const MONEY_SCALE = 2;

    /**
     * Decimals of computed quantities and packages: DECIMAL(10,3) (domain.md §7.1).
     *
     * @var int
     */
    public const QUANTITY_SCALE = 3;

    /**
     * Total digits of percentages, e.g. VAT rate 22.00.
     *
     * @var int
     */
    public const RATE_PRECISION = 5;

    /**
     * Total digits of ratios between 0 and 1, e.g. opened package ratio 0.50.
     *
     * @var int
     */
    public const RATIO_PRECISION = 3;

    /**
     * Total digits of coverage values in m² per base unit, e.g. 55.00.
     *
     * @var int
     */
    public const COVERAGE_PRECISION = 8;

    /**
     * Maximum value that can be stored on the database for unsigned integer columns.
     *
     * @var int
     */
    public const UNSIGNED_INTEGER_MAX_VALUE = 4294967295;

    /**
     * @var int
     */
    public const VAT_LENGTH = 20;

    /**
     * Length of short strings in database, e.g. role code.
     *
     * @var int
     */
    public const VERY_SHORT_STRING_MAX_LENGTH = 10;
}
