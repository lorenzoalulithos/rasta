<?php declare(strict_types=1);

namespace App\Models\Database\v1;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\Models\Database\v1\SiteFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Site (Cantiere), domain.md §3.4: if the address is null it is inherited from the customer.
 */
#[UseFactory(SiteFactory::class)]
final class Site extends Model
{
    use BelongsToTenant;
    /** @use HasFactory<SiteFactory> */
    use HasFactory;
    use HasUuids;

    /**
     * {@inheritdoc}
     */
    protected $fillable = [
        'tenant_id',
        'customer_id',
        'name',
        'address',
        'zip',
        'city',
        'province',
        'notes',
    ];

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<Quote, $this>
     */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }
}
