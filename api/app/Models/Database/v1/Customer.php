<?php declare(strict_types=1);

namespace App\Models\Database\v1;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\Models\Database\v1\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * Customer (Cliente), domain.md §3.3.
 */
#[UseFactory(CustomerFactory::class)]
final class Customer extends Model
{
    use BelongsToTenant;
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;
    use HasUuids;

    /**
     * {@inheritdoc}
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'tax_code',
        'vat_number',
        'email',
        'phone',
        'address',
        'zip',
        'city',
        'province',
        'notes',
    ];

    /**
     * @return HasMany<Site, $this>
     */
    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    /**
     * @return HasManyThrough<Quote, Site, $this>
     */
    public function quotes(): HasManyThrough
    {
        return $this->hasManyThrough(Quote::class, Site::class);
    }
}
