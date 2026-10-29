<?php declare(strict_types=1);

namespace App\Models\Database\v1;

use App\Enums\ConsumptionMode;
use App\Enums\JobMaterialRole;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\Models\Database\v1\QuoteLineMaterialFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * QuoteLineMaterial, domain.md §3.10: snapshot of the requirement computed for a line.
 */
#[UseFactory(QuoteLineMaterialFactory::class)]
final class QuoteLineMaterial extends Model
{
    use BelongsToTenant;
    /** @use HasFactory<QuoteLineMaterialFactory> */
    use HasFactory;
    use HasUuids;

    /**
     * {@inheritdoc}
     */
    protected $fillable = [
        'tenant_id',
        'quote_line_id',
        'material_id',
        'role',
        'consumption_mode',
        'consumption_per_unit',
        'apply_coats',
        'selected',
        'coverage_used',
        'required_quantity',
        'required_packages',
        'unit_cost',
        'estimated_cost',
    ];

    /**
     * @return BelongsTo<QuoteLine, $this>
     */
    public function line(): BelongsTo
    {
        return $this->belongsTo(QuoteLine::class, 'quote_line_id');
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
            'role' => JobMaterialRole::class,
            'consumption_mode' => ConsumptionMode::class,
            'consumption_per_unit' => 'decimal:3',
            'apply_coats' => 'boolean',
            'selected' => 'boolean',
            'coverage_used' => 'decimal:2',
            'required_quantity' => 'decimal:3',
            'required_packages' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'estimated_cost' => 'decimal:2',
        ];
    }
}
