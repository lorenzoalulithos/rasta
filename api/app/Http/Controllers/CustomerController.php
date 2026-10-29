<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Database\v1\Customer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Customers (Clienti), api.md "Clienti e cantieri".
 */
final class CustomerController extends Controller
{
    /**
     * GET /customers?search=: by name, with sites and quotes counts.
     *
     * @param Request $request
     *
     * @return AnonymousResourceCollection
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $search = $request->string('search')->trim()->toString();

        $customers = Customer::query()
                             ->withCount(['sites', 'quotes'])
                             ->when('' !== $search, static function (Builder $query) use ($search): void {
                                 $query->where(static function (Builder $query) use ($search): void {
                                     $query->whereLike('name', "%{$search}%")
                                           ->orWhereLike('city', "%{$search}%")
                                           ->orWhereLike('phone', "%{$search}%")
                                           ->orWhereLike('email', "%{$search}%");
                                 });
                             })
                             ->orderBy('name')
                             ->paginate($this->perPage($request));

        return CustomerResource::collection($customers);
    }

    /**
     * @param CustomerRequest $request
     *
     * @return JsonResponse
     */
    public function store(CustomerRequest $request): JsonResponse
    {
        $customer = Customer::query()->create($request->validated());

        return new CustomerResource($customer->loadCount(['sites', 'quotes']))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * @param Customer $customer
     *
     * @return CustomerResource
     */
    public function show(Customer $customer): CustomerResource
    {
        return new CustomerResource($customer->load('sites')->loadCount(['sites', 'quotes']));
    }

    /**
     * @param CustomerRequest $request
     * @param Customer        $customer
     *
     * @return CustomerResource
     */
    public function update(CustomerRequest $request, Customer $customer): CustomerResource
    {
        $customer->update($request->validated());

        return new CustomerResource($customer->loadCount(['sites', 'quotes']));
    }

    /**
     * 409 if the customer has quotes; its sites go with it.
     *
     * @param Customer $customer
     *
     * @return Response
     */
    public function destroy(Customer $customer): Response
    {
        abort_if(
            $customer->quotes()->exists(),
            Response::HTTP_CONFLICT,
            'Il cliente ha dei preventivi: non si può eliminare.'
        );

        $customer->delete();

        return response()->noContent();
    }
}
