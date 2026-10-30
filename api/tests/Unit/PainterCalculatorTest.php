<?php declare(strict_types=1);

use App\Enums\ConsumptionMode;
use App\Enums\CoverageStrategy;
use App\Enums\PackageRounding;
use App\Services\Calculator\CalculationSettings;
use App\Services\Calculator\LineInput;
use App\Services\Calculator\MaterialInput;
use App\Services\Calculator\PainterCalculator;
use Tests\Support\CalculatorFixtures;

it('reproduces domain.md §7.3: 181 m², 2 coats, avg coverage → 3 bidoni, 143.07 €', function (): void {
    $result = new PainterCalculator()->line(
        CalculatorFixtures::belissiLine([CalculatorFixtures::tempera()]),
        new CalculationSettings(),
    );
    $tempera = $result->materials[0];

    expect($result->amount)->toBe('724.00')
        ->and($tempera->coverageUsed)->toBe('9.00')
        ->and($tempera->requiredQuantity)->toBe('40.222')
        ->and($tempera->requiredPackages)->toBe('3.000')
        ->and($tempera->unitCost)->toBe('47.69')
        ->and($tempera->estimatedCost)->toBe('143.07')
        ->and($result->estimatedCost)->toBe('143.07');
});

it('keeps fractional packages with 3 decimals: 2.873 × 47.69 = 137.01 €', function (): void {
    $result = new PainterCalculator()->line(
        CalculatorFixtures::belissiLine([CalculatorFixtures::tempera()]),
        new CalculationSettings(packageRounding: PackageRounding::Fractional),
    );

    expect($result->materials[0]->requiredPackages)->toBe('2.873')
        ->and($result->materials[0]->estimatedCost)->toBe('137.01');
});

it('uses the minimum coverage: 362 / 8 = 45.25 lt → 4 bidoni', function (): void {
    $tempera = new PainterCalculator()->line(
        CalculatorFixtures::belissiLine([CalculatorFixtures::tempera()]),
        new CalculationSettings(coverageStrategy: CoverageStrategy::Min),
    )->materials[0];

    expect($tempera->coverageUsed)->toBe('8.00')
        ->and($tempera->requiredQuantity)->toBe('45.250')
        ->and($tempera->requiredPackages)->toBe('4.000')
        ->and($tempera->estimatedCost)->toBe('190.76');
});

it('uses the maximum coverage: 362 / 10 = 36.2 lt → 3 bidoni', function (): void {
    $tempera = new PainterCalculator()->line(
        CalculatorFixtures::belissiLine([CalculatorFixtures::tempera()]),
        new CalculationSettings(coverageStrategy: CoverageStrategy::Max),
    )->materials[0];

    expect($tempera->coverageUsed)->toBe('10.00')
        ->and($tempera->requiredQuantity)->toBe('36.200')
        ->and($tempera->requiredPackages)->toBe('3.000')
        ->and($tempera->estimatedCost)->toBe('143.07');
});

it('ignores the coats when apply_coats is false: fissativo 181 / 55 = 3.291 lt → 1 bidone', function (): void {
    $fissativo = new PainterCalculator()->line(
        CalculatorFixtures::belissiLine([CalculatorFixtures::fissativo()]),
        new CalculationSettings(),
    )->materials[0];

    expect($fissativo->coverageUsed)->toBe('55.00')
        ->and($fissativo->requiredQuantity)->toBe('3.291')
        ->and($fissativo->requiredPackages)->toBe('1.000')
        ->and($fissativo->estimatedCost)->toBe('103.38');
});

it('computes per_unit consumption: 181 × 0.05 kg = 9.05 kg → 2 sacchi', function (): void {
    $stucco = new PainterCalculator()->line(
        CalculatorFixtures::belissiLine([CalculatorFixtures::stucco()]),
        new CalculationSettings(),
    )->materials[0];

    expect($stucco->coverageUsed)->toBeNull()
        ->and($stucco->requiredQuantity)->toBe('9.050')
        ->and($stucco->requiredPackages)->toBe('2.000')
        ->and($stucco->estimatedCost)->toBe('20.56');
});

it('multiplies per_unit by the coats when apply_coats is true', function (): void {
    $material = new MaterialInput(
        consumptionMode: ConsumptionMode::PerUnit,
        packageSize: '1',
        costPrice: '4.90',
        consumptionPerUnit: '0.02',
        applyCoats: true,
    );

    $result = new PainterCalculator()->line(CalculatorFixtures::belissiLine([$material]), new CalculationSettings());

    expect($result->materials[0]->requiredQuantity)->toBe('7.240')
        ->and($result->materials[0]->requiredPackages)->toBe('8.000');
});

it('counts only the selected materials in the line cost', function (): void {
    $materials = [CalculatorFixtures::tempera(), CalculatorFixtures::stucco(), CalculatorFixtures::tempera(selected: false)];

    $result = new PainterCalculator()->line(CalculatorFixtures::belissiLine($materials), new CalculationSettings());
    [, , $unselected] = $result->materials;

    // 143.07 tempera + 20.56 stucco; the unselected tempera is calculated but not counted
    expect($result->materials)->toHaveCount(count($materials))
        ->and($unselected->estimatedCost)->toBe('143.07')
        ->and($result->estimatedCost)->toBe('163.63');
});

it('uses the only coverage present and gives zero without coverage or price', function (): void {
    $onlyMin = new MaterialInput(consumptionMode: ConsumptionMode::Coverage, packageSize: '14', costPrice: '47.69', coverageMin: '9');
    $noCoverage = new MaterialInput(consumptionMode: ConsumptionMode::Coverage, packageSize: '14', costPrice: '47.69');
    $noPrice = new MaterialInput(consumptionMode: ConsumptionMode::Coverage, packageSize: '14', costPrice: null, coverageMin: '9');

    $result = new PainterCalculator()->line(
        CalculatorFixtures::belissiLine([$onlyMin, $noCoverage, $noPrice]),
        new CalculationSettings(),
    );

    [$withOnlyMin, $withoutCoverage, $withoutPrice] = $result->materials;

    expect($withOnlyMin->coverageUsed)->toBe('9.00')
        ->and($withOnlyMin->requiredQuantity)->toBe('20.111')
        ->and($withoutCoverage->coverageUsed)->toBeNull()
        ->and($withoutCoverage->requiredPackages)->toBe('0.000')
        ->and($withoutPrice->requiredPackages)->toBe('2.000')
        ->and($withoutPrice->estimatedCost)->toBe('0.00');
});

it('totals the real Belissi quote: 724 + 100 + 150 = 974.00 €, margin 85.3%', function (): void {
    $calculator = new PainterCalculator();
    $settings = new CalculationSettings();
    $painting = $calculator->line(CalculatorFixtures::belissiLine([CalculatorFixtures::tempera()]), $settings);
    // Stuccature interne: 2 ore × 50.00, stucco not ticked
    $hourly = $calculator->line(new LineInput(quantity: '2', unitPrice: '50.00', isHourly: true), $settings);
    // Carteggiatura e tinteggiatura portone esterno: free line, 1 unità × 150.00
    $free = $calculator->line(new LineInput(quantity: '1', unitPrice: '150.00'), $settings);
    $lines = [$painting, $hourly, $free];

    $forfettario = $calculator->totals($lines, '0');
    $ordinario = $calculator->totals($lines, '22');

    expect($painting->hours)->toBeNull()
        ->and($hourly->amount)->toBe('100.00')
        ->and($hourly->hours)->toBe('2.000')
        ->and($free->amount)->toBe('150.00')
        ->and($forfettario->subtotal)->toBe('974.00')
        ->and($forfettario->vatAmount)->toBe('0.00')
        ->and($forfettario->total)->toBe('974.00')
        ->and($forfettario->estimatedCost)->toBe('143.07')
        ->and($forfettario->estimatedMargin)->toBe('830.93')
        ->and($forfettario->estimatedMarginPct)->toBe('85.3')
        ->and($forfettario->estimatedHours)->toBe('2.000')
        ->and($ordinario->vatAmount)->toBe('214.28')
        ->and($ordinario->total)->toBe('1188.28');
});

it('has no margin percentage and no hours on an empty quote', function (): void {
    $totals = new PainterCalculator()->totals([], '0');

    expect($totals->subtotal)->toBe('0.00')
        ->and($totals->estimatedMarginPct)->toBeNull()
        ->and($totals->estimatedHours)->toBeNull();
});

it('gives the line margin of §7.3: 724.00 − 143.07 = 580.93 (80.2%)', function (): void {
    $calculator = new PainterCalculator();
    $line = $calculator->line(CalculatorFixtures::belissiLine([CalculatorFixtures::tempera()]), new CalculationSettings());
    $totals = $calculator->totals([$line], '0');

    expect($totals->estimatedMargin)->toBe('580.93')
        ->and($totals->estimatedMarginPct)->toBe('80.2');
});
