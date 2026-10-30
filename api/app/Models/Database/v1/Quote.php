<?php declare(strict_types=1);

namespace App\Models\Database\v1;

use App\Enums\QuoteStatus;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\Models\Database\v1\QuoteFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

/**
 * Quote (Preventivo), domain.md §3.8: numbered per tenant and year.
 *
 * Enum casts, declared for PHPStan (Larastan does not read them from casts()):
 *
 * @property QuoteStatus $status
 */
#[UseFactory(QuoteFactory::class)]
final class Quote extends Model
{
    use BelongsToTenant;
    /** @use HasFactory<QuoteFactory> */
    use HasFactory;
    use HasUuids;

    /**
     * {@inheritdoc}
     */
    protected $fillable = [
        'tenant_id',
        'site_id',
        'number',
        'year',
        'issued_at',
        'expires_at',
        'status',
        'vat_rate',
        'payment_terms',
        'footer_text',
        'private_notes',
        'subtotal',
        'vat_amount',
        'total',
        'estimated_cost',
        'estimated_margin',
        'accepted_at',
        'completed_at',
    ];

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * The customer, through the site.
     *
     * @return HasOneThrough<Customer, Site, $this>
     */
    public function customer(): HasOneThrough
    {
        return $this->hasOneThrough(Customer::class, Site::class, 'id', 'id', 'site_id', 'customer_id');
    }

    /**
     * Lines, in their display order.
     *
     * @return HasMany<QuoteLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(QuoteLine::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * {@inheritdoc}
     */
    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'year' => 'integer',
            'issued_at' => 'date',
            'expires_at' => 'date',
            'status' => QuoteStatus::class,
            'vat_rate' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'estimated_cost' => 'decimal:2',
            'estimated_margin' => 'decimal:2',
            'accepted_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
