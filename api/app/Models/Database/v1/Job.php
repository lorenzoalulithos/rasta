<?php declare(strict_types=1);

namespace App\Models\Database\v1;

use App\Enums\JobUnit;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\Models\Database\v1\JobFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Job (Lavorazione), domain.md §3.6: a service sold in quotes. Not a queue job.
 *
 * Enum casts, declared for PHPStan (Larastan does not read them from casts()):
 *
 * @property JobUnit $unit
 */
#[UseFactory(JobFactory::class)]
final class Job extends Model
{
    use BelongsToTenant;
    /** @use HasFactory<JobFactory> */
    use HasFactory;
    use HasUuids;

    /**
     * {@inheritdoc}
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'unit',
        'unit_price',
        'default_coats',
        'avg_time_notes',
        'estimated_hours_per_unit',
        'show_on_quote_pdf',
        'sort_order',
        'active',
    ];

    /**
     * Suggested materials, in their display order.
     *
     * @return HasMany<JobMaterial, $this>
     */
    public function jobMaterials(): HasMany
    {
        return $this->hasMany(JobMaterial::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<QuoteLine, $this>
     */
    public function quoteLines(): HasMany
    {
        return $this->hasMany(QuoteLine::class);
    }

    /**
     * {@inheritdoc}
     */
    protected function casts(): array
    {
        return [
            'unit' => JobUnit::class,
            'unit_price' => 'decimal:2',
            'default_coats' => 'integer',
            'estimated_hours_per_unit' => 'decimal:3',
            'show_on_quote_pdf' => 'boolean',
            'sort_order' => 'integer',
            'active' => 'boolean',
        ];
    }
}
