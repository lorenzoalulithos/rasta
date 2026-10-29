<?php declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Database\v1\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Domain models carry a `tenant_id` (domain.md §2.1).
 *
 * The global scope that filters by the authenticated user's tenant comes with A1.2.
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
}
