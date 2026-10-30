<?php declare(strict_types=1);

namespace App\Http\Requests;

use Database\Constants\MigrationConstants;
use Illuminate\Foundation\Http\FormRequest;

/**
 * PATCH /quotes/{id}: the fields of the header (api.md). Lines have their own endpoints.
 */
final class UpdateQuoteRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'private_notes' => ['nullable', 'string', 'max:' . MigrationConstants::SUPER_LONG_STRING_MAX_LENGTH],
            'payment_terms' => ['nullable', 'string', 'max:255'],
            'vat_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'issued_at' => ['sometimes', 'date'],
        ];
    }

    /**
     * True when the request changes more than the private notes.
     *
     * @return bool
     */
    public function changesDocument(): bool
    {
        return [] !== array_diff(array_keys($this->validated()), ['private_notes']);
    }
}
