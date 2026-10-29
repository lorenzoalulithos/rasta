<?php declare(strict_types=1);

namespace App\Models\Database\v1;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\Models\Database\v1\PurchaseLineFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PurchaseLine, domain.md §3.13.
 */
#[UseFactory(PurchaseLineFactory::class)]
final class PurchaseLine extends Model
{
    use BelongsToTenant;
    /** @use HasFactory<PurchaseLineFactory> */
    use HasFactory;
    use HasUuids;

    /**
     * {@inheritdoc}
     */
    protected $fillable = [
        'tenant_id',
        'purchase_id',
        'material_id',
        'packages',
        'unit_cost',
    ];

    /**
     * @return BelongsTo<Purchase, $this>
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

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
            'packages' => 'integer',
            'unit_cost' => 'decimal:2',
        ];
    }
}
