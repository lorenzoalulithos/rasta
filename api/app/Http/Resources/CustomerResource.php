<?php declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Database\v1\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Customer (Cliente), domain.md §3.3.
 *
 * @mixin Customer
 */
final class CustomerResource extends JsonResource
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
            'tax_code' => $this->tax_code,
            'vat_number' => $this->vat_number,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'zip' => $this->zip,
            'city' => $this->city,
            'province' => $this->province,
            'notes' => $this->notes,
            'sites_count' => $this->whenCounted('sites'),
            'quotes_count' => $this->whenCounted('quotes'),
            'sites' => SiteResource::collection($this->whenLoaded('sites')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
