<?php declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Database\v1\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A stock movement, domain.md §3.12.
 *
 * @mixin StockMovement
 */
final class StockMovementResource extends JsonResource
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
            'material_id' => $this->material_id,
            'material' => $this->whenLoaded('material', fn (): array => [
                'name' => $this->material?->name,
                'package_label' => $this->material?->package_label,
            ]),
            'type' => $this->type,
            'closed_delta' => $this->closed_delta,
            'opened_delta' => $this->opened_delta,
            'quote_id' => $this->quote_id,
            'purchase_id' => $this->purchase_id,
            'notes' => $this->notes,
            'occurred_at' => $this->occurred_at,
        ];
    }
}
