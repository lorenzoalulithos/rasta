<?php declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Database\v1\Material;
use App\Models\Database\v1\User;
use App\Services\Stock\StockService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A row of the stock list (flows.md §3.1): the material, its three numbers, colour and what to buy.
 *
 * Expects the material with `stock` loaded and the `withCommittedPackages` scope.
 *
 * @mixin Material
 */
final class StockResource extends JsonResource
{
    /**
     * @param Request $request
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $service = app(StockService::class);
        $committed = $this->resource->getAttribute('committed_packages');
        $figures = $service->figures(
            $this->stock,
            self::openedPackageRatio($request),
            is_numeric($committed) ? (string) $committed : null,
        );

        return [
            'material_id' => $this->id,
            'material' => [
                'id' => $this->id,
                'name' => $this->name,
                'category' => $this->category,
                'kind' => $this->kind,
                'base_unit' => $this->base_unit,
                'package_label' => $this->package_label,
            ],
            'min_stock' => (float) $this->min_stock,
            ...$figures,
            'to_buy' => $service->toBuy($figures['available'], $this->min_stock),
            'status' => $service->status($figures['available'], $this->min_stock),
            'commitments' => $this->when(
                (bool) $request->attributes->get('stock_with_commitments', false),
                fn () => $service->commitments($this->resource),
            ),
        ];
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
