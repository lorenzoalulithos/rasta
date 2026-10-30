<?php declare(strict_types=1);

namespace Tests\Support;

use App\Enums\ConsumptionMode;
use App\Services\Calculator\LineInput;
use App\Services\Calculator\MaterialInput;

/**
 * Materials and lines of the Belissi quote, domain.md §7.3, with the seed data (docs/seed).
 */
final class CalculatorFixtures
{
    /**
     * Coats of the Belissi line.
     *
     * @var int
     */
    public const TWO_COATS = 2;

    /**
     * Tempera Wallcream: bidone 14 lt, coverage 8/10 m²/lt, 47.69 €, multiplied by coats.
     *
     * @param bool $selected
     *
     * @return MaterialInput
     */
    public static function tempera(bool $selected = true): MaterialInput
    {
        return new MaterialInput(
            consumptionMode: ConsumptionMode::Coverage,
            packageSize: '14',
            costPrice: '47.69',
            coverageMin: '8',
            coverageMax: '10',
            applyCoats: true,
            selected: $selected,
            key: 'tempera',
        );
    }

    /**
     * Fissativo Idrofis: bidone 14 lt, coverage 50/60 m²/lt, 103.38 €, one pass whatever the coats.
     *
     * @return MaterialInput
     */
    public static function fissativo(): MaterialInput
    {
        return new MaterialInput(
            consumptionMode: ConsumptionMode::Coverage,
            packageSize: '14',
            costPrice: '103.38',
            coverageMin: '50',
            coverageMax: '60',
            key: 'fissativo',
        );
    }

    /**
     * Stucco gesso Tamstucco: sacco 5 kg, 0.05 kg per m², 10.28 €.
     *
     * @return MaterialInput
     */
    public static function stucco(): MaterialInput
    {
        return new MaterialInput(
            consumptionMode: ConsumptionMode::PerUnit,
            packageSize: '5',
            costPrice: '10.28',
            consumptionPerUnit: '0.05',
            key: 'stucco',
        );
    }

    /**
     * Imbiancatura vano scala: 181 m², 2 coats, 4.00 €/m² → 724.00 €.
     *
     * @param array<MaterialInput> $materials
     *
     * @return LineInput
     */
    public static function belissiLine(array $materials): LineInput
    {
        return new LineInput(quantity: '181', unitPrice: '4.00', coats: self::TWO_COATS, materials: $materials);
    }
}
