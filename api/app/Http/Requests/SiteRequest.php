<?php declare(strict_types=1);

namespace App\Http\Requests;

use Database\Constants\MigrationConstants;
use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /customers/{id}/sites and PATCH /sites/{id}.
 *
 * A null address means "same address as the customer" (domain.md §3.4).
 */
final class SiteRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $required = $this->isMethod('PATCH') ? 'sometimes' : 'required';

        return [
            'name' => [$required, 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'zip' => ['nullable', 'string', 'max:' . MigrationConstants::CAP_STRING_LENGTH],
            'city' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:' . MigrationConstants::VERY_SHORT_STRING_MAX_LENGTH],
            'notes' => ['nullable', 'string', 'max:' . MigrationConstants::SUPER_LONG_STRING_MAX_LENGTH],
        ];
    }
}
