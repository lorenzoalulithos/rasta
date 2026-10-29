<?php declare(strict_types=1);

namespace App\Models\Database\v1;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\Models\Database\v1\StockFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stock (Giacenza), domain.md §3.11: one row per material, keyed by `material_id`.
 *
 * on_hand, committed, available and to_buy are derived on read (StockService, A3.1).
 */
#[UseFactory(StockFactory::class)]
final class Stock extends Model
{
    use BelongsToTenant;
    /** @use HasFactory<StockFactory> */
    use HasFactory;

    /**
     * {@inheritdoc}
     */
    public $incrementing = false;

    /**
     * {@inheritdoc}
     */
    protected $primaryKey = 'material_id';

    /**
     * {@inheritdoc}
     */
    protected $keyType = 'string';

    /**
     * {@inheritdoc}
     */
    protected $fillable = [
        'material_id',
        'tenant_id',
        'closed_packages',
        'opened_packages',
    ];

    /**
     * @return BelongsTo<Material, $this>
     */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    /**
     * {@inheritdoc}
     */
    protected function casts(): array
    {
        return [
            'closed_packages' => 'integer',
            'opened_packages' => 'integer',
        ];
    }
}
