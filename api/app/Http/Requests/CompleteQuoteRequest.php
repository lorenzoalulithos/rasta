<?php declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\MaterialKind;
use App\Models\Scopes\TenantScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * POST /quotes/{id}/complete: `{ consumptions: [{ material_id, closed, opened }] }`.
 *
 * Any material of the tenant but tools, also not in the quote ("+ Altro materiale usato"), each once.
 */
final class CompleteQuoteRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string|Exists>>
     */
    public function rules(): array
    {
        return [
            'consumptions' => ['present', 'array'],
            'consumptions.*.material_id' => [
                'required',
                'uuid',
                'distinct',
                Rule::exists('materials', 'id')
                    ->where('tenant_id', TenantScope::currentTenantId())
                    ->whereNot('kind', MaterialKind::Tool->value),
            ],
            'consumptions.*.closed' => ['required', 'integer', 'min:0'],
            'consumptions.*.opened' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return list<array{material_id: string, closed: int, opened: int}>
     */
    public function consumptions(): array
    {
        /** @var list<array{material_id: string, closed: int|string, opened: int|string}> $consumptions */
        $consumptions = $this->validated('consumptions', []);

        return array_map(static fn (array $consumption): array => [
            'material_id' => $consumption['material_id'],
            'closed' => (int) $consumption['closed'],
            'opened' => (int) $consumption['opened'],
        ], $consumptions);
    }
}
