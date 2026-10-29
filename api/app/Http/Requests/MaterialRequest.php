<?php declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\BaseUnit;
use App\Enums\MaterialCategory;
use App\Enums\MaterialKind;
use Database\Constants\MigrationConstants;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * POST /materials and PATCH /materials/{id}, fields of domain.md §3.5.
 */
final class MaterialRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string|Enum>>
     */
    public function rules(): array
    {
        $required = $this->isMethod('PATCH') ? 'sometimes' : 'required';

        return [
            'name' => [$required, 'string', 'max:255'],
            'category' => [$required, Rule::enum(MaterialCategory::class)],
            'kind' => [$required, Rule::enum(MaterialKind::class)],
            'base_unit' => [$required, Rule::enum(BaseUnit::class)],
            'package_size' => [$required, 'numeric', 'gt:0'],
            'package_label' => [$required, 'string', 'max:' . MigrationConstants::MEDIUM_STRING_MAX_LENGTH],
            'list_price' => ['nullable', 'numeric', 'min:0'],
            'discount_notes' => ['nullable', 'string', 'max:' . MigrationConstants::SHORT_STRING_MAX_LENGTH],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'shipping_excluded' => ['sometimes', 'boolean'],
            'coverage_min' => ['nullable', 'numeric', 'gt:0'],
            'coverage_max' => ['nullable', 'numeric', 'gt:0', 'gte:coverage_min'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'min_stock' => ['sometimes', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:' . MigrationConstants::SUPER_LONG_STRING_MAX_LENGTH],
        ];
    }
}
