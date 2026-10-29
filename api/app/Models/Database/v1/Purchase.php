<?php declare(strict_types=1);

namespace App\Models\Database\v1;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\Models\Database\v1\PurchaseFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Purchase (Acquisto), domain.md §3.13: only a "paid" flag, no due dates.
 */
#[UseFactory(PurchaseFactory::class)]
final class Purchase extends Model
{
    use BelongsToTenant;
    /** @use HasFactory<PurchaseFactory> */
    use HasFactory;
    use HasUuids;

    /**
     * {@inheritdoc}
     */
    protected $fillable = [
        'tenant_id',
        'supplier',
        'purchased_at',
        'paid',
        'total',
        'notes',
    ];

    /**
     * @return HasMany<PurchaseLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseLine::class);
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
            'purchased_at' => 'date',
            'paid' => 'boolean',
            'total' => 'decimal:2',
        ];
    }
}
