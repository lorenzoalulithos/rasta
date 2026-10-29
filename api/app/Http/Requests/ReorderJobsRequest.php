<?php declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Scopes\TenantScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * POST /jobs/reorder: `{ ids: [] }`, the jobs take the order of the array.
 */
final class ReorderJobsRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string|Exists>>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => [
                'required',
                'uuid',
                'distinct',
                Rule::exists('jobs', 'id')->where('tenant_id', TenantScope::currentTenantId()),
            ],
        ];
    }
}
