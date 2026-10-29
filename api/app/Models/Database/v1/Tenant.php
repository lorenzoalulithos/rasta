<?php declare(strict_types=1);

namespace App\Models\Database\v1;

use App\Enums\CoverageStrategy;
use App\Enums\PackageRounding;
use App\Enums\TaxRegime;
use App\Enums\Trade;
use Database\Factories\Models\Database\v1\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tenant (Azienda), domain.md §3.1.
 */
#[UseFactory(TenantFactory::class)]
final class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;
    use HasUuids;

    /**
     * {@inheritdoc}
     */
    protected $fillable = [
        'name',
        'address',
        'zip',
        'city',
        'province',
        'vat_number',
        'tax_code',
        'email',
        'phone',
        'logo_path',
        'tax_regime',
        'default_vat_rate',
        'quote_validity_days',
        'quote_footer_text',
        'default_payment_terms',
        'coverage_strategy',
        'opened_package_ratio',
        'package_rounding',
        'trade',
    ];

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<Customer, $this>
     */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    /**
     * @return HasMany<Material, $this>
     */
    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }

    /**
     * @return HasMany<Job, $this>
     */
    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class);
    }

    /**
     * @return HasMany<Quote, $this>
     */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    /**
     * @return HasMany<Purchase, $this>
     */
    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    /**
     * {@inheritdoc}
     */
    protected function casts(): array
    {
        return [
            'tax_regime' => TaxRegime::class,
            'default_vat_rate' => 'decimal:2',
            'quote_validity_days' => 'integer',
            'coverage_strategy' => CoverageStrategy::class,
            'opened_package_ratio' => 'decimal:2',
            'package_rounding' => PackageRounding::class,
            'trade' => Trade::class,
        ];
    }
}
