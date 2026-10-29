<?php declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Database\v1\Job;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Job (Lavorazione), domain.md §3.6, with its suggested materials.
 *
 * @mixin Job
 */
final class JobResource extends JsonResource
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
            'unit' => $this->unit,
            'unit_price' => (float) $this->unit_price,
            'default_coats' => $this->default_coats,
            'avg_time_notes' => $this->avg_time_notes,
            'estimated_hours_per_unit' => null === $this->estimated_hours_per_unit ? null : (float) $this->estimated_hours_per_unit,
            'show_on_quote_pdf' => $this->show_on_quote_pdf,
            'sort_order' => $this->sort_order,
            'active' => $this->active,
            'materials' => JobMaterialResource::collection($this->whenLoaded('jobMaterials')),
        ];
    }
}
