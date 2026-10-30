<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StorePurchaseRequest;
use App\Http\Resources\PurchaseResource;
use App\Models\Database\v1\Purchase;
use App\Services\Stock\PurchaseRecorder;
use Database\Constants\MigrationConstants;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Purchases (Acquisti), api.md "Magazzino" and flows.md §3.3–3.4.
 */
final class PurchaseController extends Controller
{
    /**
     * GET /purchases?paid=0|1: newest first ("Da pagare" / "Pagati").
     *
     * @param Request $request
     *
     * @return AnonymousResourceCollection
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $purchases = Purchase::query()
                             ->withCount('lines')
                             ->when($request->has('paid'), static fn (Builder $query) => $query->where('paid', $request->boolean('paid')))
                             ->latest('purchased_at')
                             ->latest()
                             ->paginate($this->perPage($request));

        return PurchaseResource::collection($purchases);
    }

    /**
     * POST /purchases: loads the stock and, for the materials in `update_prices`, the list price.
     *
     * @param StorePurchaseRequest $request
     * @param PurchaseRecorder     $recorder
     *
     * @return JsonResponse
     */
    public function store(StorePurchaseRequest $request, PurchaseRecorder $recorder): JsonResponse
    {
        /** @var list<array{material_id: string, packages: int|string, unit_cost: int|float|string}> $lines */
        $lines = $request->validated('lines');
        /** @var list<string> $updatePrices */
        $updatePrices = $request->validated('update_prices', []);

        $purchase = $recorder->record(
            [
                'supplier' => $request->filled('supplier') ? $request->string('supplier')->trim()->toString() : null,
                'purchased_at' => $request->string('purchased_at')->toString(),
                'paid' => $request->boolean('paid'),
                'notes' => $request->filled('notes') ? $request->string('notes')->toString() : null,
            ],
            array_map(static fn (array $line): array => [
                'material_id' => $line['material_id'],
                'packages' => (int) $line['packages'],
                'unit_cost' => (string) $line['unit_cost'],
            ], $lines),
            $updatePrices,
        );

        return new PurchaseResource($purchase->load('lines.material'))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * @param Purchase $purchase
     *
     * @return PurchaseResource
     */
    public function show(Purchase $purchase): PurchaseResource
    {
        return new PurchaseResource($purchase->load('lines.material'));
    }

    /**
     * PATCH /purchases/{id}: only `paid` and `notes`, the lines already moved the stock.
     *
     * @param Request  $request
     * @param Purchase $purchase
     *
     * @return PurchaseResource
     */
    public function update(Request $request, Purchase $purchase): PurchaseResource
    {
        $purchase->update($request->validate([
            'paid' => ['sometimes', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:' . MigrationConstants::SUPER_LONG_STRING_MAX_LENGTH],
        ]));

        return new PurchaseResource($purchase->load('lines.material'));
    }
}
