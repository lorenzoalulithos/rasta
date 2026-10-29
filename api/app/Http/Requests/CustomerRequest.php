<?php declare(strict_types=1);

namespace App\Http\Requests;

use Database\Constants\MigrationConstants;
use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /customers and PATCH /customers/{id}: only `name` is required (flows.md §2.2, §5).
 */
final class CustomerRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $required = $this->isMethod('PATCH') ? 'sometimes' : 'required';

        return [
            'name' => [$required, 'string', 'max:255'],
            'tax_code' => ['nullable', 'string', 'max:' . MigrationConstants::VAT_LENGTH],
            'vat_number' => ['nullable', 'string', 'max:' . MigrationConstants::VAT_LENGTH],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:' . MigrationConstants::SHORT_STRING_MAX_LENGTH],
            'address' => ['nullable', 'string', 'max:255'],
            'zip' => ['nullable', 'string', 'max:' . MigrationConstants::CAP_STRING_LENGTH],
            'city' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:' . MigrationConstants::VERY_SHORT_STRING_MAX_LENGTH],
            'notes' => ['nullable', 'string', 'max:' . MigrationConstants::SUPER_LONG_STRING_MAX_LENGTH],
        ];
    }
}
