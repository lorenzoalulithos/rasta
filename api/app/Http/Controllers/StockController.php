<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\MaterialKind;
use App\Http\Requests\AdjustStockRequest;
use App\Http\Resources\StockMovementResource;
use App\Http\Resources\StockResource;
use App\Models\Database\v1\Material;
use App\Models\Database\v1\StockMovement;
use App\Models\Database\v1\User;
use App\Services\Stock\StockService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Stock (Magazzino), api.md "Magazzino" and flows.md §3.1–3.2.
 */
final class StockController extends Controller
{
    /**
     * @param StockService $stock
     */
    public function __construct(private readonly StockService $stock)
    {
    }

    /**
     * GET /stock?filter=to_buy&category=&search=: materials that are stocked (not tools, not archived).
     *
     * @param Request $request
     *
     * @return AnonymousResourceCollection
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $search = $request->string('search')->trim()->toString();
        $toBuy = 'to_buy' === $request->string('filter')->toString();

        $materials = Material::query()
                             ->select('materials.*')
                             ->with('stock')
                             ->withCommittedPackages()
                             ->whereNull('materials.archived_at')
                             ->where('materials.kind', '!=', MaterialKind::Tool->value)
                             ->when($toBuy, fn (Builder $query) => $this->stock->whereToBuy($query, self::ratio($request)))
                             ->when($request->filled('category'), static fn (Builder $query) => $query->where('materials.category', $request->string('category')->toString()))
                             ->when('' !== $search, static fn (Builder $query) => $query->whereLike('materials.name', "%{$search}%"))
                             ->orderBy('materials.name')
                             ->paginate($this->perPage($request));

        // The "da comprare" list also shows which sites need each material
        $request->attributes->set('stock_with_commitments', $toBuy);

        return StockResource::collection($materials);
    }

    /**
     * POST /stock/{materialId}/adjust: "vado giù, do un'occhiata e sistemo".
     *
     * @param AdjustStockRequest $request
     * @param Material           $material
     *
     * @return StockResource
     */
    public function adjust(AdjustStockRequest $request, Material $material): StockResource
    {
        $this->stock->adjust(
            $material,
            $request->integer('closed'),
            $request->integer('opened'),
            $request->filled('notes') ? $request->string('notes')->toString() : null,
        );

        return new StockResource(self::withFigures($material));
    }

    /**
     * PATCH /stock/{materialId}: `{ min_stock }`, shortcut on the material.
     *
     * @param Request  $request
     * @param Material $material
     *
     * @return StockResource
     */
    public function update(Request $request, Material $material): StockResource
    {
        $validated = $request->validate(['min_stock' => ['required', 'numeric', 'min:0']]);
        $material->update($validated);

        return new StockResource(self::withFigures($material));
    }

    /**
     * GET /stock/movements?material_id=, newest first.
     *
     * @param Request $request
     *
     * @return AnonymousResourceCollection
     */
    public function movements(Request $request): AnonymousResourceCollection
    {
        $movements = StockMovement::query()
                                  ->with('material')
                                  ->when($request->filled('material_id'), static fn (Builder $query) => $query->where('material_id', $request->string('material_id')->toString()))
                                  ->latest('occurred_at')
                                  ->paginate($this->perPage($request));

        return StockMovementResource::collection($movements);
    }

    /**
     * @param Material $material
     *
     * @return Material
     */
    private static function withFigures(Material $material): Material
    {
        /** @var Material */
        return Material::query()->with('stock')->withCommittedPackages()->whereKey($material->id)->firstOrFail();
    }

    /**
     * @param Request $request
     *
     * @return string
     */
    private static function ratio(Request $request): string
    {
        $user = $request->user();

        return $user instanceof User ? (string) $user->tenant?->opened_package_ratio : '0.50';
    }
}
