<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\TenantRequest;
use App\Http\Resources\TenantResource;
use App\Models\Database\v1\Tenant;
use App\Models\Database\v1\User;
use Illuminate\Http\Request;

/**
 * GET/PATCH /tenant: company data and settings of the authenticated user (flows.md §7).
 */
final class TenantController extends Controller
{
    /**
     * @param Request $request
     *
     * @return TenantResource
     */
    public function show(Request $request): TenantResource
    {
        return new TenantResource($this->tenant($request));
    }

    /**
     * @param TenantRequest $request
     *
     * @return TenantResource
     */
    public function update(TenantRequest $request): TenantResource
    {
        $tenant = $this->tenant($request);
        $tenant->update($request->validated());

        return new TenantResource($tenant);
    }

    /**
     * @param Request $request
     *
     * @return Tenant
     */
    private function tenant(Request $request): Tenant
    {
        /** @var User $user */
        $user = $request->user();

        /** @var Tenant */
        return $user->tenant;
    }
}
