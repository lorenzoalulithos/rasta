<?php declare(strict_types=1);

namespace App\Models\Database\v1;

use App\Enums\BaseUnit;
use App\Enums\MaterialCategory;
use App\Enums\MaterialKind;
use App\Enums\QuoteStatus;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\Models\Database\v1\MaterialFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Material (Materiale), domain.md §3.5.
 *
 * Enum casts, declared for PHPStan (Larastan does not read them from casts()):
 *
 * @property MaterialCategory $category
 * @property MaterialKind $kind
 * @property BaseUnit $base_unit
 */
#[UseFactory(MaterialFactory::class)]
final class Material extends Model
{
    use BelongsToTenant;
    /** @use HasFactory<MaterialFactory> */
    use HasFactory;
    use HasUuids;

    /**
     * {@inheritdoc}
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'category',
        'kind',
        'base_unit',
        'package_size',
        'package_label',
        'list_price',
        'discount_notes',
        'cost_price',
        'shipping_excluded',
        'coverage_min',
        'coverage_max',
        'supplier',
        'min_stock',
        'notes',
    ];

    /**
     * @return HasOne<Stock, $this>
     */
    public function stock(): HasOne
    {
        return $this->hasOne(Stock::class);
    }

    /**
     * @return HasMany<JobMaterial, $this>
     */
    public function jobMaterials(): HasMany
    {
        return $this->hasMany(JobMaterial::class);
    }

    /**
     * @return HasMany<QuoteLineMaterial, $this>
     */
    public function quoteLineMaterials(): HasMany
    {
        return $this->hasMany(QuoteLineMaterial::class);
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Selected line materials of accepted quotes: what commits the stock (domain.md §5.9).
     *
     * @return HasMany<QuoteLineMaterial, $this>
     */
    public function commitments(): HasMany
    {
        return $this->quoteLineMaterials()
                    ->where('selected', true)
                    ->whereHas('line.quote', static function (Builder $query): void {
                        $query->where('status', QuoteStatus::Accepted);
                    });
    }

    /**
     * Adds `committed_packages`: the sum of required packages of the commitments.
     *
     * @param Builder<Material> $query
     *
     * @return void
     */
    public function scopeWithCommittedPackages(Builder $query): void
    {
        $query->withSum('commitments as committed_packages', 'required_packages');
    }

    /**
     * {@inheritdoc}
     */
    protected function casts(): array
    {
        return [
            'category' => MaterialCategory::class,
            'kind' => MaterialKind::class,
            'base_unit' => BaseUnit::class,
            'package_size' => 'decimal:3',
            'list_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'shipping_excluded' => 'boolean',
            'coverage_min' => 'decimal:2',
            'coverage_max' => 'decimal:2',
            'min_stock' => 'decimal:2',
            'archived_at' => 'datetime',
        ];
    }
}
