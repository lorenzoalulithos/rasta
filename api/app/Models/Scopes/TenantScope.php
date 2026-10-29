<?php declare(strict_types=1);

namespace App\Models\Scopes;

use App\Models\Database\v1\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Limits queries to the tenant of the authenticated user (domain.md §2.1).
 *
 * Without an authenticated user (console, seeders, tests that set no user) nothing is filtered.
 *
 * @implements Scope<Model>
 */
final class TenantScope implements Scope
{
    /**
     * @param Builder<covariant Model> $builder
     * @param Model                    $model
     *
     * @return void
     */
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = self::currentTenantId();

        if (null !== $tenantId) {
            $builder->where($model->qualifyColumn('tenant_id'), $tenantId);
        }
    }

    /**
     * @return string|null
     */
    public static function currentTenantId(): ?string
    {
        if (!Auth::hasUser()) {
            return null;
        }

        $user = Auth::user();

        return $user instanceof User ? $user->tenant_id : null;
    }
}
