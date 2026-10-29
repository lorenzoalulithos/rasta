<?php declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Database\v1\JobMaterial;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Suggested material of a job, domain.md §3.7.
 *
 * @mixin JobMaterial
 */
final class JobMaterialResource extends JsonResource
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
            'material' => new MaterialResource($this->whenLoaded('material')),
            'role' => $this->role,
            'consumption_mode' => $this->consumption_mode,
            'consumption_per_unit' => null === $this->consumption_per_unit ? null : (float) $this->consumption_per_unit,
            'apply_coats' => $this->apply_coats,
            'selected_by_default' => $this->selected_by_default,
            'sort_order' => $this->sort_order,
        ];
    }
}
