<?php declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ConsumptionMode;
use App\Enums\JobUnit;
use App\Models\Scopes\TenantScope;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\In;

/**
 * Validation of a quote line payload (api.md, POST lines), shared by lines and preview-calculate.
 */
final class QuoteLineRules
{
    /**
     * Most coats a line can have.
     *
     * @var int
     */
    private const MAX_COATS = 5;

    /**
     * @param string $prefix       e.g. "" for a line, "lines.*." inside preview-calculate
     * @param bool   $creating     quantity and, for free lines, description and price are required
     * @param bool   $withExisting the payload may carry the `id` of a saved line, which works like its PATCH
     *
     * @return array<string, array<int, string|Enum|Exists|In>>
     */
    public static function rules(string $prefix = '', bool $creating = true, bool $withExisting = false): array
    {
        $tenantId = TenantScope::currentTenantId();
        $required = match (true) {
            $withExisting => "required_without:{$prefix}id",
            $creating => 'required',
            default => 'sometimes',
        };
        $freeLine = match (true) {
            $withExisting => "required_without_all:{$prefix}job_id,{$prefix}id",
            $creating => "required_without:{$prefix}job_id",
            default => 'sometimes',
        };

        return [
            "{$prefix}job_id" => ['nullable', 'uuid', Rule::exists('jobs', 'id')->where('tenant_id', $tenantId)],
            "{$prefix}description" => [$freeLine, 'nullable', 'string', 'max:255'],
            "{$prefix}unit" => ['sometimes', Rule::enum(JobUnit::class)],
            "{$prefix}quantity" => [$required, 'numeric', 'gt:0'],
            "{$prefix}coats" => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_COATS],
            "{$prefix}unit_price" => [$freeLine, 'nullable', 'numeric', 'min:0'],
            "{$prefix}materials" => ['sometimes', 'array'],
            "{$prefix}materials.*.material_id" => [
                'required',
                'uuid',
                Rule::exists('materials', 'id')->where('tenant_id', $tenantId)->whereNull('archived_at'),
            ],
            "{$prefix}materials.*.selected" => ['sometimes', 'boolean'],
            "{$prefix}materials.*.consumption_mode" => [
                'sometimes',
                Rule::in([ConsumptionMode::Coverage->value, ConsumptionMode::PerUnit->value]),
            ],
            "{$prefix}materials.*.consumption_per_unit" => ['nullable', 'numeric', 'gt:0'],
            "{$prefix}materials.*.apply_coats" => ['sometimes', 'boolean'],
        ];
    }
}
