<?php declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Database\v1\Material;
use App\Models\Database\v1\User;
use App\Services\Stock\StockService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Material (Materiale), domain.md §3.5. `stock` only when the stock relation is loaded,
 * together with the `withCommittedPackages` scope.
 *
 * @mixin Material
 */
final class MaterialResource extends JsonResource
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
            'category' => $this->category,
            'kind' => $this->kind,
            'base_unit' => $this->base_unit,
            'package_size' => (float) $this->package_size,
            'package_label' => $this->package_label,
            'list_price' => self::decimal($this->list_price),
            'discount_notes' => $this->discount_notes,
            'cost_price' => self::decimal($this->cost_price),
            'shipping_excluded' => $this->shipping_excluded,
            'coverage_min' => self::decimal($this->coverage_min),
            'coverage_max' => self::decimal($this->coverage_max),
            'supplier' => $this->supplier,
            'min_stock' => (float) $this->min_stock,
            'notes' => $this->notes,
            'stock' => $this->when(
                $this->relationLoaded('stock'),
                fn (): array => app(StockService::class)->figures(
                    $this->stock,
                    self::openedPackageRatio($request),
                    self::committedPackages($this->resource),
                )
            ),
        ];
    }

    /**
     * @param float|string|null $value decimal cast, a string at runtime
     *
     * @return float|null
     */
    private static function decimal(float|string|null $value): ?float
    {
        return null === $value ? null : (float) $value;
    }

    /**
     * `committed_packages` added by the withCommittedPackages scope, null when nothing is committed.
     *
     * @param Material $material
     *
     * @return string|null
     */
    private static function committedPackages(Material $material): ?string
    {
        $committed = $material->getAttribute('committed_packages');

        return is_numeric($committed) ? (string) $committed : null;
    }

    /**
     * @param Request $request
     *
     * @return string
     */
    private static function openedPackageRatio(Request $request): string
    {
        $user = $request->user();

        return $user instanceof User ? (string) $user->tenant?->opened_package_ratio : '0.50';
    }
}
