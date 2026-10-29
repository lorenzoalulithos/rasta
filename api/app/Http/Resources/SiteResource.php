<?php declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Database\v1\Site;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Site (Cantiere), domain.md §3.4: a null address means "same as the customer".
 *
 * @mixin Site
 */
final class SiteResource extends JsonResource
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
            'customer_id' => $this->customer_id,
            'name' => $this->name,
            'address' => $this->address,
            'zip' => $this->zip,
            'city' => $this->city,
            'province' => $this->province,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
