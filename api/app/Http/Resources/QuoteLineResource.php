<?php declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Database\v1\QuoteLine;
use App\Models\Database\v1\QuoteLineMaterial;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A quote line with its materials (api.md, "Formato del calcolo").
 *
 * @mixin QuoteLine
 */
final class QuoteLineResource extends JsonResource
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
            'job_id' => $this->job_id,
            'description' => $this->description,
            'unit' => $this->unit,
            'quantity' => (float) $this->quantity,
            'coats' => $this->coats,
            'unit_price' => (float) $this->unit_price,
            'amount' => (float) $this->amount,
            'sort_order' => $this->sort_order,
            'materials' => QuoteLineMaterialResource::collection($this->whenLoaded('materials')),
            'estimated_cost' => $this->whenLoaded(
                'materials',
                fn (): float => (float) $this->materials
                    ->where('selected', true)
                    ->sum(static fn (QuoteLineMaterial $material): float => (float) $material->estimated_cost)
            ),
        ];
    }
}
