<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\QuoteStatus;
use App\Http\Requests\MaterialRequest;
use App\Http\Resources\MaterialResource;
use App\Models\Database\v1\Material;
use App\Models\Database\v1\QuoteLineMaterial;
use App\Models\Database\v1\Stock;
use App\Models\Database\v1\StockMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Materials (Materiali) with their stock, api.md "Catalogo".
 */
final class MaterialController extends Controller
{
    /**
     * How many movements the detail shows.
     *
     * @var int
     */
    private const LATEST_MOVEMENTS = 10;

    /**
     * GET /materials?search=&category=&kind=.
     *
     * @param Request $request
     *
     * @return AnonymousResourceCollection
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $search = $request->string('search')->trim()->toString();

        $materials = Material::query()
                             ->with('stock')
                             ->withCommittedPackages()
                             ->when('' !== $search, static function (Builder $query) use ($search): void {
                                 $query->where(static function (Builder $query) use ($search): void {
                                     $query->whereLike('name', "%{$search}%")
                                           ->orWhereLike('supplier', "%{$search}%");
                                 });
                             })
                             ->when($request->filled('category'), static fn (Builder $query) => $query->where('category', $request->string('category')->toString()))
                             ->when($request->filled('kind'), static fn (Builder $query) => $query->where('kind', $request->string('kind')->toString()))
                             ->orderBy('name')
                             ->paginate($this->perPage($request));

        return MaterialResource::collection($materials);
    }

    /**
     * Creates the material with its stock at zero.
     *
     * @param MaterialRequest $request
     *
     * @return JsonResponse
     */
    public function store(MaterialRequest $request): JsonResponse
    {
        $material = DB::transaction(static function () use ($request): Material {
            $material = Material::query()->create($request->validated());
            Stock::query()->create(['material_id' => $material->id, 'tenant_id' => $material->tenant_id]);

            return $material;
        });

        return new MaterialResource(self::withStock($material))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * With stock, commitments (accepted quotes and packages) and the latest movements.
     *
     * @param Request  $request
     * @param Material $material
     *
     * @return JsonResponse
     */
    public function show(Request $request, Material $material): JsonResponse
    {
        $commitments = $material->commitments()
                                ->with('line.quote.site.customer')
                                ->get()
                                ->groupBy(static fn (QuoteLineMaterial $item): string => (string) $item->line?->quote_id)
                                ->map(static function ($items): array {
                                    $quote = $items->firstOrFail()->line?->quote;

                                    return [
                                        'quote' => [
                                            'id' => $quote?->id,
                                            'number' => $quote?->number,
                                            'year' => $quote?->year,
                                            'customer' => $quote?->site?->customer?->name,
                                            'site' => $quote?->site?->name,
                                        ],
                                        'packages' => (float) $items->sum(static fn (QuoteLineMaterial $item): float => (float) $item->required_packages),
                                    ];
                                })
                                ->values();

        $movements = $material->stockMovements()
                              ->latest('occurred_at')
                              ->limit(self::LATEST_MOVEMENTS)
                              ->get()
                              ->map(static fn (StockMovement $movement): array => [
                                  'id' => $movement->id,
                                  'type' => $movement->type,
                                  'closed_delta' => $movement->closed_delta,
                                  'opened_delta' => $movement->opened_delta,
                                  'quote_id' => $movement->quote_id,
                                  'purchase_id' => $movement->purchase_id,
                                  'notes' => $movement->notes,
                                  'occurred_at' => $movement->occurred_at,
                              ]);

        return response()->json([
            'data' => [
                ...new MaterialResource(self::withStock($material))->resolve($request),
                'commitments' => $commitments,
                'movements' => $movements,
            ],
        ]);
    }

    /**
     * @param MaterialRequest $request
     * @param Material        $material
     *
     * @return MaterialResource
     */
    public function update(MaterialRequest $request, Material $material): MaterialResource
    {
        $material->update($request->validated());

        return new MaterialResource(self::withStock($material));
    }

    /**
     * 409 while the material is in quotes that are not closed (draft, sent, accepted).
     *
     * @param Material $material
     *
     * @return Response
     */
    public function destroy(Material $material): Response
    {
        $inOpenQuotes = $material->quoteLineMaterials()
                                 ->whereHas('line.quote', static function (Builder $query): void {
                                     $query->whereIn('status', [QuoteStatus::Draft, QuoteStatus::Sent, QuoteStatus::Accepted]);
                                 })
                                 ->exists();

        abort_if($inOpenQuotes, Response::HTTP_CONFLICT, 'Il materiale è in preventivi aperti: non si può eliminare.');

        abort_if(
            $material->quoteLineMaterials()->exists(),
            Response::HTTP_CONFLICT,
            'Il materiale è nei preventivi chiusi: non si può eliminare.'
        );

        $material->delete();

        return response()->noContent();
    }

    /**
     * @param Material $material
     *
     * @return Material
     */
    private static function withStock(Material $material): Material
    {
        /** @var Material */
        return Material::query()->with('stock')->withCommittedPackages()->findOrFail($material->id);
    }
}
