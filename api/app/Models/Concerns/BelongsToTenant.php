<?php declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Database\v1\Tenant;
use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Domain models carry a `tenant_id` (domain.md §2.1): queries are scoped to the authenticated
 * user's tenant, and new records get that tenant when none is set.
 */
trait BelongsToTenant
{
    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return void
     */
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(static function (Model $model): void {
            if (null === $model->getAttribute('tenant_id')) {
                $model->setAttribute('tenant_id', TenantScope::currentTenantId());
            }
        });
    }
}
