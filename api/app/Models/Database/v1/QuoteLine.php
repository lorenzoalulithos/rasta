<?php declare(strict_types=1);

namespace App\Models\Database\v1;

use App\Enums\JobUnit;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\Models\Database\v1\QuoteLineFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * QuoteLine (Riga preventivo), domain.md §3.9: `job_id` null for free lines.
 *
 * Enum casts, declared for PHPStan (Larastan does not read them from casts()):
 *
 * @property JobUnit $unit
 */
#[UseFactory(QuoteLineFactory::class)]
final class QuoteLine extends Model
{
    use BelongsToTenant;
    /** @use HasFactory<QuoteLineFactory> */
    use HasFactory;
    use HasUuids;

    /**
     * {@inheritdoc}
     */
    protected $fillable = [
        'tenant_id',
        'quote_id',
        'job_id',
        'description',
        'unit',
        'quantity',
        'coats',
        'unit_price',
        'amount',
        'sort_order',
    ];

    /**
     * @return BelongsTo<Quote, $this>
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    /**
     * @return BelongsTo<Job, $this>
     */
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    /**
     * @return HasMany<QuoteLineMaterial, $this>
     */
    public function materials(): HasMany
    {
        return $this->hasMany(QuoteLineMaterial::class);
    }

    /**
     * {@inheritdoc}
     */
    protected function casts(): array
    {
        return [
            'unit' => JobUnit::class,
            'quantity' => 'decimal:2',
            'coats' => 'integer',
            'unit_price' => 'decimal:2',
            'amount' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }
}
