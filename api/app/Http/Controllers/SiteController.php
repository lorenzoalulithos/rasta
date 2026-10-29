<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SiteRequest;
use App\Http\Resources\SiteResource;
use App\Models\Database\v1\Customer;
use App\Models\Database\v1\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Sites (Cantieri) of a customer, api.md "Clienti e cantieri".
 */
final class SiteController extends Controller
{
    /**
     * @param Customer $customer
     *
     * @return AnonymousResourceCollection
     */
    public function index(Customer $customer): AnonymousResourceCollection
    {
        return SiteResource::collection($customer->sites()->orderBy('name')->get());
    }

    /**
     * @param SiteRequest $request
     * @param Customer    $customer
     *
     * @return JsonResponse
     */
    public function store(SiteRequest $request, Customer $customer): JsonResponse
    {
        $site = $customer->sites()->create([
            ...$request->validated(),
            'tenant_id' => $customer->tenant_id,
        ]);

        return new SiteResource($site)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * @param SiteRequest $request
     * @param Site        $site
     *
     * @return SiteResource
     */
    public function update(SiteRequest $request, Site $site): SiteResource
    {
        $site->update($request->validated());

        return new SiteResource($site);
    }

    /**
     * 409 if the site has quotes.
     *
     * @param Site $site
     *
     * @return Response
     */
    public function destroy(Site $site): Response
    {
        abort_if(
            $site->quotes()->exists(),
            Response::HTTP_CONFLICT,
            'Il cantiere ha dei preventivi: non si può eliminare.'
        );

        $site->delete();

        return response()->noContent();
    }
}
