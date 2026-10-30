<?php declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Database\v1\QuoteLineMaterial;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A material of a quote line with its computed requirement (api.md, "Formato del calcolo").
 *
 * @mixin QuoteLineMaterial
 */
final class QuoteLineMaterialResource extends JsonResource
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
                'base_unit' => $this->material?->base_unit,
            ]),
            'role' => $this->role,
            'consumption_mode' => $this->consumption_mode,
            'consumption_per_unit' => null === $this->consumption_per_unit ? null : (float) $this->consumption_per_unit,
            'apply_coats' => $this->apply_coats,
            'selected' => $this->selected,
            'coverage_used' => null === $this->coverage_used ? null : (float) $this->coverage_used,
            'required_quantity' => (float) $this->required_quantity,
            'required_packages' => (float) $this->required_packages,
            'unit_cost' => (float) $this->unit_cost,
            'estimated_cost' => (float) $this->estimated_cost,
        ];
    }
}
