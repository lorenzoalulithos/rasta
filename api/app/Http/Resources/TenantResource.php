<?php declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Database\v1\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * All fields of Tenant, domain.md §3.1.
 *
 * @mixin Tenant
 */
final class TenantResource extends JsonResource
{
    /**
     * @param Request $request
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
            'zip' => $this->zip,
            'city' => $this->city,
            'province' => $this->province,
            'vat_number' => $this->vat_number,
            'tax_code' => $this->tax_code,
            'email' => $this->email,
            'phone' => $this->phone,
            'logo_path' => $this->logo_path,
            'tax_regime' => $this->tax_regime,
            'default_vat_rate' => (float) $this->default_vat_rate,
            'quote_validity_days' => $this->quote_validity_days,
            'quote_footer_text' => $this->quote_footer_text,
            'default_payment_terms' => $this->default_payment_terms,
            'coverage_strategy' => $this->coverage_strategy,
            'opened_package_ratio' => (float) $this->opened_package_ratio,
            'package_rounding' => $this->package_rounding,
            'trade' => $this->trade,
        ];
    }
}
