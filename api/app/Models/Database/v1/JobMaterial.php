<?php declare(strict_types=1);

namespace App\Models\Database\v1;

use App\Enums\ConsumptionMode;
use App\Enums\JobMaterialRole;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\Models\Database\v1\JobMaterialFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * JobMaterial (Materiale suggerito per lavorazione), domain.md §3.7: a proposal, not a fixed bill of materials.
 *
 * Enum casts, declared for PHPStan (Larastan does not read them from casts()):
 *
 * @property JobMaterialRole $role
 * @property ConsumptionMode $consumption_mode
 */
#[UseFactory(JobMaterialFactory::class)]
final class JobMaterial extends Model
{
    use BelongsToTenant;
    /** @use HasFactory<JobMaterialFactory> */
    use HasFactory;
    use HasUuids;

    /**
     * {@inheritdoc}
     */
    protected $fillable = [
        'tenant_id',
        'job_id',
        'material_id',
        'role',
        'consumption_mode',
        'consumption_per_unit',
        'apply_coats',
        'selected_by_default',
        'sort_order',
    ];

    /**
     * @return BelongsTo<Job, $this>
     */
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
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
            'selected_by_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
