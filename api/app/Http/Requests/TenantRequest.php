<?php declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CoverageStrategy;
use App\Enums\PackageRounding;
use App\Enums\TaxRegime;
use Database\Constants\MigrationConstants;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * PATCH /tenant: partial update of the settings (flows.md §7). `trade` is not editable.
 */
final class TenantRequest extends FormRequest
{
    /**
     * Longest quote validity accepted, in days.
     *
     * @var int
     */
    private const MAX_QUOTE_VALIDITY_DAYS = 365;

    /**
     * @return array<string, array<int, string|Enum>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'zip' => ['nullable', 'string', 'max:' . MigrationConstants::CAP_STRING_LENGTH],
            'city' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:' . MigrationConstants::VERY_SHORT_STRING_MAX_LENGTH],
            'vat_number' => ['nullable', 'string', 'max:' . MigrationConstants::VAT_LENGTH],
            'tax_code' => ['nullable', 'string', 'max:' . MigrationConstants::VAT_LENGTH],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:' . MigrationConstants::SHORT_STRING_MAX_LENGTH],
            'tax_regime' => ['sometimes', Rule::enum(TaxRegime::class)],
            'default_vat_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'quote_validity_days' => ['sometimes', 'integer', 'min:1', 'max:' . self::MAX_QUOTE_VALIDITY_DAYS],
            'quote_footer_text' => ['nullable', 'string', 'max:' . MigrationConstants::SUPER_LONG_STRING_MAX_LENGTH],
            'default_payment_terms' => ['nullable', 'string', 'max:255'],
            'coverage_strategy' => ['sometimes', Rule::enum(CoverageStrategy::class)],
            'opened_package_ratio' => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'package_rounding' => ['sometimes', Rule::enum(PackageRounding::class)],
        ];
    }
}
