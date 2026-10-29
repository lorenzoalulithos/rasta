<?php declare(strict_types=1);

namespace App\Models\Database\v1;

use App\Enums\StockMovementType;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\Models\Database\v1\StockMovementFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * StockMovement (Movimento di magazzino), domain.md §3.12: every stock change goes through here.
 */
#[UseFactory(StockMovementFactory::class)]
final class StockMovement extends Model
{
    use BelongsToTenant;
    /** @use HasFactory<StockMovementFactory> */
    use HasFactory;
    use HasUuids;

    /**
     * {@inheritdoc}
     */
    protected $fillable = [
        'tenant_id',
        'material_id',
        'type',
        'closed_delta',
        'opened_delta',
        'quote_id',
        'purchase_id',
        'notes',
        'occurred_at',
    ];

    /**
     * @return BelongsTo<Material, $this>
     */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    /**
     * Set for `consumption` movements.
     *
     * @return BelongsTo<Quote, $this>
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    /**
     * Set for `purchase` movements.
     *
     * @return BelongsTo<Purchase, $this>
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    /**
     * {@inheritdoc}
     */
    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'closed_delta' => 'integer',
            'opened_delta' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }
}
