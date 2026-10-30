<?php declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Database\v1\Purchase;
use App\Models\Database\v1\PurchaseLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Purchase (Acquisto), domain.md §3.13, with its lines when loaded.
 *
 * @mixin Purchase
 */
final class PurchaseResource extends JsonResource
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
            'supplier' => $this->supplier,
            'purchased_at' => $this->purchased_at->toDateString(),
            'paid' => $this->paid,
            'total' => null === $this->total ? null : (float) $this->total,
            'notes' => $this->notes,
            'lines_count' => $this->whenCounted('lines'),
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(static fn (PurchaseLine $line): array => [
                'id' => $line->id,
                'material_id' => $line->material_id,
                'material' => [
                    'name' => $line->material?->name,
                    'package_label' => $line->material?->package_label,
                ],
                'packages' => $line->packages,
                'unit_cost' => (float) $line->unit_cost,
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
