<?php declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Scopes\TenantScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * POST /quotes: `{ site_id }` (flows.md §2.2).
 */
final class StoreQuoteRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string|Exists>>
     */
    public function rules(): array
    {
        return [
            'site_id' => [
                'required',
                'uuid',
                Rule::exists('sites', 'id')->where('tenant_id', TenantScope::currentTenantId()),
            ],
        ];
    }
}
