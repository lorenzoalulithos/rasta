<?php declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ConsumptionMode;
use App\Enums\JobMaterialRole;
use App\Enums\JobUnit;
use App\Models\Scopes\TenantScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Exists;

/**
 * POST /jobs and PATCH /jobs/{id}, domain.md §3.6–3.7.
 *
 * `materials[]` (in display order) replaces the whole list of suggested materials when present.
 */
final class JobRequest extends FormRequest
{
    /**
     * Most coats a job can include.
     *
     * @var int
     */
    private const MAX_COATS = 5;

    /**
     * @return array<string, array<int, string|Enum|Exists>>
     */
    public function rules(): array
    {
        $required = $this->isMethod('PATCH') ? 'sometimes' : 'required';

        return [
            'name' => [$required, 'string', 'max:255'],
            'unit' => [$required, Rule::enum(JobUnit::class)],
            'unit_price' => [$required, 'numeric', 'min:0'],
            'default_coats' => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_COATS],
            'avg_time_notes' => ['nullable', 'string', 'max:255'],
            'estimated_hours_per_unit' => ['nullable', 'numeric', 'min:0'],
            'show_on_quote_pdf' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
            'materials' => ['sometimes', 'array'],
            'materials.*.material_id' => [
                'required',
                'uuid',
                'distinct',
                Rule::exists('materials', 'id')->where('tenant_id', TenantScope::currentTenantId()),
            ],
            'materials.*.role' => ['required', Rule::enum(JobMaterialRole::class)],
            'materials.*.consumption_mode' => ['required', Rule::enum(ConsumptionMode::class)],
            'materials.*.consumption_per_unit' => ['nullable', 'required_if:materials.*.consumption_mode,per_unit', 'numeric', 'gt:0'],
            'materials.*.apply_coats' => ['sometimes', 'boolean'],
            'materials.*.selected_by_default' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Suggested materials as sent, or null when the list is not part of the request.
     *
     * @return array<int, array<string, mixed>>|null
     */
    public function materials(): ?array
    {
        if (!$this->has('materials')) {
            return null;
        }

        /** @var array<int, array<string, mixed>> $materials */
        $materials = array_values($this->validated('materials', []));

        return $materials;
    }

    /**
     * Job fields only, without `materials`.
     *
     * @return array<string, mixed>
     */
    public function jobAttributes(): array
    {
        /** @var array<string, mixed> $attributes */
        $attributes = $this->safe()->except('materials');

        return $attributes;
    }
}
