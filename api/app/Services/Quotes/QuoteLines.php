<?php declare(strict_types=1);

namespace App\Services\Quotes;

use App\Enums\ConsumptionMode;
use App\Enums\JobMaterialRole;
use App\Enums\JobUnit;
use App\Enums\QuoteStatus;
use App\Models\Database\v1\Job;
use App\Models\Database\v1\JobMaterial;
use App\Models\Database\v1\Material;
use App\Models\Database\v1\Quote;
use App\Models\Database\v1\QuoteLine;
use App\Models\Database\v1\QuoteLineMaterial;
use App\Models\Database\v1\Tenant;
use App\Services\Calculator\CalculationSettings;
use App\Services\Calculator\CalculatorInterface;
use App\Services\Calculator\CalculatorResolver;
use App\Services\Calculator\Decimal;
use App\Services\Calculator\LineInput;
use App\Services\Calculator\LineResult;
use App\Services\Calculator\MaterialInput;
use App\Services\Calculator\QuoteTotals;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Quote lines: build, calculate and save them with their material snapshot (domain.md §3.9–3.10, §7).
 *
 * `make()` never writes: the same lines serve POST/PATCH lines and preview-calculate.
 */
final class QuoteLines
{
    /**
     * @param CalculatorResolver $calculators
     */
    public function __construct(private readonly CalculatorResolver $calculators)
    {
    }

    /**
     * Builds and calculates a line from a validated payload, without saving it.
     *
     * New line, or a line whose job changes: defaults and suggested materials from the job.
     * Existing line on the same job: its values and its material snapshot, overridden by the payload.
     *
     * @param Quote                $quote
     * @param array<string, mixed> $payload  validated line fields
     * @param QuoteLine|null       $existing the line being edited
     *
     * @throws ValidationException
     *
     * @return BuiltLine
     */
    public function make(Quote $quote, array $payload, ?QuoteLine $existing = null): BuiltLine
    {
        $jobId = array_key_exists('job_id', $payload) ? $payload['job_id'] : $existing?->job_id;
        $job = null === $jobId ? null : Job::query()->with('jobMaterials.material')->whereKey((string) $jobId)->firstOrFail();
        $base = null !== $existing && $existing->job_id === $jobId ? $existing : null;

        /** @var array<int, array<string, mixed>> $items */
        $items = $payload['materials'] ?? [];

        if (null === $job && [] !== $items) {
            throw ValidationException::withMessages(['materials' => 'Le righe libere non hanno materiali.']);
        }

        $specs = $this->applyItems(
            null !== $base ? self::snapshotSpecs($base) : self::suggestedSpecs($job),
            $items,
        );

        $line = new QuoteLine([
            'tenant_id' => $quote->tenant_id,
            'quote_id' => $quote->id,
            'job_id' => $job?->id,
            'description' => $payload['description'] ?? $base->description ?? $job->name ?? $existing->description ?? null,
            'unit' => $job->unit ?? $payload['unit'] ?? $existing->unit ?? JobUnit::Piece,
            'quantity' => $payload['quantity'] ?? $existing->quantity ?? null,
            'coats' => array_key_exists('coats', $payload) ? $payload['coats'] : ($base->coats ?? $job->default_coats ?? null),
            'unit_price' => $payload['unit_price'] ?? $base->unit_price ?? $job->unit_price ?? $existing->unit_price ?? null,
            'sort_order' => $existing->sort_order ?? 0,
        ]);

        return $this->calculate($quote, $line, $job, $specs);
    }

    /**
     * Saves a built line (and its materials), replacing `$existing` when given.
     *
     * @param BuiltLine      $built
     * @param QuoteLine|null $existing
     *
     * @return QuoteLine
     */
    public function save(BuiltLine $built, ?QuoteLine $existing = null): QuoteLine
    {
        /** @var iterable<QuoteLineMaterial> $materials */
        $materials = $built->line->getRelation('materials');

        return DB::transaction(static function () use ($built, $existing, $materials): QuoteLine {
            $attributes = $built->line->getAttributes();

            if (null === $existing) {
                $line = $built->line;
                $line->setAttribute(
                    'sort_order',
                    (int) QuoteLine::query()->where('quote_id', $line->quote_id)->max('sort_order') + 1
                );
                $line->save();
            } else {
                $line = $existing;
                $line->fill([...$attributes, 'sort_order' => $existing->sort_order])->save();
                $line->materials()->delete();
            }

            foreach ($materials as $material) {
                $line->materials()->create($material->getAttributes());
            }

            return $line->refresh()->load(['materials.material', 'job']);
        });
    }

    /**
     * Recomputes the stored totals of the quote from its saved lines.
     *
     * @param Quote $quote
     *
     * @return void
     */
    public function refreshTotals(Quote $quote): void
    {
        $quote->load(['lines.materials', 'lines.job']);
        $totals = $this->totals($quote, $quote->lines->map(fn (QuoteLine $line): LineResult => $this->savedResult($line))->all());

        $quote->forceFill([
            'subtotal' => $totals->subtotal,
            'vat_amount' => $totals->vatAmount,
            'total' => $totals->total,
            'estimated_cost' => $totals->estimatedCost,
            'estimated_margin' => $totals->estimatedMargin,
        ])->save();
    }

    /**
     * Totals of a set of calculated lines with the quote VAT.
     *
     * @param Quote             $quote
     * @param array<LineResult> $results
     *
     * @return QuoteTotals
     */
    public function totals(Quote $quote, array $results): QuoteTotals
    {
        return $this->calculator($quote)->totals($results, $quote->vat_rate);
    }

    /**
     * "Ricalcola": current coverage and prices for every material; rule realigned to the job where
     * the material is still suggested. Descriptions, quantities and prices to the customer stay.
     *
     * @param Quote $quote
     *
     * @return void
     */
    public function recalculate(Quote $quote): void
    {
        $quote->load(['lines.materials.material', 'lines.job.jobMaterials']);

        DB::transaction(function () use ($quote): void {
            foreach ($quote->lines as $line) {
                if (null === $line->job) {
                    continue;
                }

                $suggested = $line->job->jobMaterials->keyBy('material_id');
                $specs = $line->materials->map(static function (QuoteLineMaterial $item) use ($suggested): MaterialSpec {
                    /** @var JobMaterial|null $rule */
                    $rule = $suggested->get($item->material_id);

                    return new MaterialSpec(
                        material: $item->material ?? Material::query()->findOrFail($item->material_id),
                        role: $rule->role ?? $item->role,
                        consumptionMode: $rule->consumption_mode ?? $item->consumption_mode,
                        consumptionPerUnit: null !== $rule ? self::nullableString($rule->consumption_per_unit) : $item->consumption_per_unit,
                        applyCoats: $rule->apply_coats ?? $item->apply_coats,
                        selected: $item->selected,
                    );
                })->all();

                $copy = new QuoteLine($line->only(['tenant_id', 'quote_id', 'job_id', 'description', 'unit', 'quantity', 'coats', 'unit_price', 'sort_order']));
                $this->save($this->calculate($quote, $copy, $line->job, $specs), $line);
            }

            $this->refreshTotals($quote);
        });
    }

    /**
     * Refuses changes to accepted, rejected and completed quotes; brings a sent quote back to draft.
     *
     * @param Quote $quote
     *
     * @return void
     */
    public static function assertEditable(Quote $quote): void
    {
        abort_if(
            !$quote->status->isEditable(),
            Response::HTTP_CONFLICT,
            "Il preventivo è {$quote->status->label()}: righe e materiali non si possono più cambiare."
        );
    }

    /**
     * A sent quote goes back to draft when its document changes (domain.md §6).
     *
     * @param Quote $quote
     *
     * @return void
     */
    public static function backToDraft(Quote $quote): void
    {
        if (QuoteStatus::Sent === $quote->status) {
            $quote->forceFill(['status' => QuoteStatus::Draft])->save();
        }
    }

    /**
     * Estimated hours of a saved line (domain.md §7.4), null when not computable.
     *
     * @param QuoteLine $line
     *
     * @return numeric-string|null
     */
    public static function hours(QuoteLine $line): ?string
    {
        if (JobUnit::Hour === $line->unit) {
            return Decimal::round(Decimal::of($line->quantity), Decimal::QUANTITY);
        }

        $perUnit = $line->job?->estimated_hours_per_unit;

        return null === $perUnit
            ? null
            : Decimal::round(bcmul(Decimal::of($line->quantity), Decimal::of($perUnit), Decimal::WORKING_SCALE), Decimal::QUANTITY);
    }

    /**
     * @param Quote                   $quote
     * @param QuoteLine               $line  attributes set, amount and materials filled here
     * @param Job|null                $job
     * @param array<int, MaterialSpec> $specs
     *
     * @return BuiltLine
     */
    private function calculate(Quote $quote, QuoteLine $line, ?Job $job, array $specs): BuiltLine
    {
        $inputs = array_map(static fn (MaterialSpec $spec): MaterialInput => new MaterialInput(
            consumptionMode: $spec->consumptionMode,
            packageSize: Decimal::of($spec->material->package_size),
            costPrice: $spec->fromSnapshot ? $spec->snapshotUnitCost : self::nullableString($spec->material->cost_price),
            coverageMin: $spec->fromSnapshot ? $spec->snapshotCoverage : self::nullableString($spec->material->coverage_min),
            coverageMax: $spec->fromSnapshot ? $spec->snapshotCoverage : self::nullableString($spec->material->coverage_max),
            consumptionPerUnit: $spec->consumptionPerUnit,
            applyCoats: $spec->applyCoats,
            selected: $spec->selected,
        ), $specs);

        $result = $this->calculator($quote)->line(
            new LineInput(
                quantity: Decimal::of($line->quantity),
                unitPrice: Decimal::of($line->unit_price),
                coats: $line->coats,
                materials: $inputs,
                isHourly: JobUnit::Hour === $line->unit,
                estimatedHoursPerUnit: self::nullableString($job?->estimated_hours_per_unit),
            ),
            $this->settings($quote),
        );

        $line->setAttribute('amount', $result->amount);
        $line->setRelation('job', $job);
        $line->setRelation('materials', collect($result->materials)->values()->map(
            static function ($material, int $position) use ($specs, $line): QuoteLineMaterial {
                $spec = $specs[$position];
                $item = new QuoteLineMaterial([
                    'tenant_id' => $line->tenant_id,
                    'material_id' => $spec->material->id,
                    'role' => $spec->role,
                    'consumption_mode' => $spec->consumptionMode,
                    'consumption_per_unit' => $spec->consumptionPerUnit,
                    'apply_coats' => $spec->applyCoats,
                    'selected' => $spec->selected,
                    'coverage_used' => $material->coverageUsed,
                    'required_quantity' => $material->requiredQuantity,
                    'required_packages' => $material->requiredPackages,
                    'unit_cost' => $material->unitCost,
                    'estimated_cost' => $material->estimatedCost,
                    'sort_order' => $position,
                ]);
                $item->setRelation('material', $spec->material);

                return $item;
            }
        ));

        return new BuiltLine($line, $result);
    }

    /**
     * Suggested materials of the job that go into a line: no checklist, no "none", no archived.
     *
     * @param Job|null $job
     *
     * @return array<int, MaterialSpec>
     */
    private static function suggestedSpecs(?Job $job): array
    {
        if (null === $job) {
            return [];
        }

        return $job->jobMaterials
            ->filter(static fn (JobMaterial $suggestion): bool => JobMaterialRole::Checklist !== $suggestion->role
                && ConsumptionMode::None !== $suggestion->consumption_mode
                && null !== $suggestion->material
                && null === $suggestion->material->archived_at)
            ->map(static fn (JobMaterial $suggestion): MaterialSpec => new MaterialSpec(
                material: $suggestion->material ?? Material::query()->findOrFail($suggestion->material_id),
                role: $suggestion->role,
                consumptionMode: $suggestion->consumption_mode,
                consumptionPerUnit: self::nullableString($suggestion->consumption_per_unit),
                applyCoats: $suggestion->apply_coats,
                selected: $suggestion->selected_by_default,
            ))
            ->values()
            ->all();
    }

    /**
     * Materials already in a saved line, keeping coverage and cost of the snapshot.
     *
     * @param QuoteLine $line
     *
     * @return array<int, MaterialSpec>
     */
    private static function snapshotSpecs(QuoteLine $line): array
    {
        $line->loadMissing('materials.material');

        return $line->materials->map(static fn (QuoteLineMaterial $item): MaterialSpec => new MaterialSpec(
            material: $item->material ?? Material::query()->findOrFail($item->material_id),
            role: $item->role,
            consumptionMode: $item->consumption_mode,
            consumptionPerUnit: $item->consumption_per_unit,
            applyCoats: $item->apply_coats,
            selected: $item->selected,
            snapshotCoverage: $item->coverage_used,
            snapshotUnitCost: $item->unit_cost,
            fromSnapshot: true,
        ))->values()->all();
    }

    /**
     * `materials[]` of the payload: corrects the materials already there, adds the new ones.
     *
     * @param array<int, MaterialSpec>         $specs
     * @param array<int, array<string, mixed>> $items
     *
     * @throws ValidationException
     *
     * @return array<int, MaterialSpec>
     */
    private function applyItems(array $specs, array $items): array
    {
        foreach ($items as $index => $item) {
            $materialId = (string) $item['material_id'];
            $position = self::positionOf($specs, $materialId);

            if (null !== $position) {
                $spec = $specs[$position];
                $spec->selected = (bool) ($item['selected'] ?? $spec->selected);
                $spec->consumptionMode = isset($item['consumption_mode']) ? ConsumptionMode::from((string) $item['consumption_mode']) : $spec->consumptionMode;
                $spec->consumptionPerUnit = array_key_exists('consumption_per_unit', $item) ? self::nullableString($item['consumption_per_unit']) : $spec->consumptionPerUnit;
                $spec->applyCoats = (bool) ($item['apply_coats'] ?? $spec->applyCoats);

                continue;
            }

            $specs[] = self::handAdded(Material::query()->whereNull('archived_at')->findOrFail($materialId), $item, $index);
        }

        return $specs;
    }

    /**
     * "+ Altro materiale": `secondary`, coverage when the material has it, otherwise per unit.
     *
     * @param Material             $material
     * @param array<string, mixed> $item
     * @param int                  $index    position in `materials[]`, for the error key
     *
     * @throws ValidationException
     *
     * @return MaterialSpec
     */
    private static function handAdded(Material $material, array $item, int $index): MaterialSpec
    {
        $hasCoverage = null !== $material->coverage_min || null !== $material->coverage_max;
        $mode = isset($item['consumption_mode'])
            ? ConsumptionMode::from((string) $item['consumption_mode'])
            : ($hasCoverage ? ConsumptionMode::Coverage : ConsumptionMode::PerUnit);
        $perUnit = self::nullableString($item['consumption_per_unit'] ?? null);

        if (ConsumptionMode::Coverage === $mode && !$hasCoverage) {
            throw ValidationException::withMessages(["materials.{$index}.consumption_mode" => "{$material->name} non ha una resa: scrivi il consumo per unità."]);
        }

        if (ConsumptionMode::PerUnit === $mode && null === $perUnit) {
            throw ValidationException::withMessages(["materials.{$index}.consumption_per_unit" => "Scrivi quanto {$material->name} serve per unità."]);
        }

        return new MaterialSpec(
            material: $material,
            role: JobMaterialRole::Secondary,
            consumptionMode: $mode,
            consumptionPerUnit: $perUnit,
            applyCoats: (bool) ($item['apply_coats'] ?? false),
            selected: (bool) ($item['selected'] ?? true),
        );
    }

    /**
     * @param array<int, MaterialSpec> $specs
     * @param string                   $materialId
     *
     * @return int|null
     */
    private static function positionOf(array $specs, string $materialId): ?int
    {
        foreach ($specs as $position => $spec) {
            if ($spec->material->id === $materialId) {
                return $position;
            }
        }

        return null;
    }

    /**
     * Result of a saved line from its snapshot, for the quote totals.
     *
     * @param QuoteLine $line
     *
     * @return LineResult
     */
    private function savedResult(QuoteLine $line): LineResult
    {
        $cost = '0';

        foreach ($line->materials as $material) {
            if ($material->selected) {
                $cost = bcadd($cost, Decimal::of($material->estimated_cost), Decimal::MONEY);
            }
        }

        return new LineResult(
            amount: Decimal::of($line->amount),
            materials: [],
            estimatedCost: $cost,
            hours: self::hours($line),
        );
    }

    /**
     * @param Quote $quote
     *
     * @return CalculatorInterface
     */
    private function calculator(Quote $quote): CalculatorInterface
    {
        return $this->calculators->for(self::tenant($quote));
    }

    /**
     * @param Quote $quote
     *
     * @return CalculationSettings
     */
    private function settings(Quote $quote): CalculationSettings
    {
        return CalculationSettings::fromTenant(self::tenant($quote));
    }

    /**
     * @param Quote $quote
     *
     * @return Tenant
     */
    private static function tenant(Quote $quote): Tenant
    {
        $quote->loadMissing('tenant');

        /** @var Tenant */
        return $quote->tenant;
    }

    /**
     * @param mixed $value
     *
     * @return string|null
     */
    private static function nullableString(mixed $value): ?string
    {
        return is_numeric($value) ? (string) $value : null;
    }
}
