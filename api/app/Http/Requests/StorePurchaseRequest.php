<?php declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\MaterialKind;
use App\Models\Scopes\TenantScope;
use Database\Constants\MigrationConstants;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\In;

/**
 * POST /purchases: `{ supplier?, purchased_at, paid, notes?, lines: [{ material_id, packages, unit_cost }], update_prices?: [material_id] }`.
 *
 * Materials must be stocked ones of the tenant (not tools, not archived); `update_prices` only names materials of the lines.
 */
final class StorePurchaseRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string|Exists|In>>
     */
    public function rules(): array
    {
        $materials = $this->input('lines.*.material_id');

        return [
            'supplier' => ['nullable', 'string', 'max:255'],
            'purchased_at' => ['required', 'date'],
            'paid' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:' . MigrationConstants::SUPER_LONG_STRING_MAX_LENGTH],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.material_id' => [
                'required',
                'uuid',
                Rule::exists('materials', 'id')
                    ->where('tenant_id', TenantScope::currentTenantId())
                    ->whereNull('archived_at')
                    ->whereNot('kind', MaterialKind::Tool->value),
            ],
            'lines.*.packages' => ['required', 'integer', 'min:1'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'update_prices' => ['sometimes', 'array'],
            'update_prices.*' => ['uuid', Rule::in(is_array($materials) ? $materials : [])],
        ];
    }
}
