<?php declare(strict_types=1);

use App\Enums\BaseUnit;
use App\Enums\CoverageStrategy;
use App\Enums\JobMaterialRole;
use App\Enums\JobUnit;
use App\Enums\PackageRounding;
use App\Enums\QuoteStatus;
use App\Enums\StockMovementType;
use App\Enums\TaxRegime;
use App\Enums\Trade;
use App\Models\Database\v1\Customer;
use App\Models\Database\v1\Job;
use App\Models\Database\v1\JobMaterial;
use App\Models\Database\v1\Material;
use App\Models\Database\v1\Purchase;
use App\Models\Database\v1\PurchaseLine;
use App\Models\Database\v1\Quote;
use App\Models\Database\v1\QuoteLine;
use App\Models\Database\v1\QuoteLineMaterial;
use App\Models\Database\v1\Site;
use App\Models\Database\v1\Stock;
use App\Models\Database\v1\StockMovement;
use App\Models\Database\v1\Tenant;
use App\Models\Database\v1\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

// domain.md §3.1: quotes expire after 90 days by default
const DEFAULT_QUOTE_VALIDITY_DAYS = 90;

// Stock of the Belissi example: 2 closed bidoni + 1 opened
const BELISSI_CLOSED_PACKAGES = 2;

it('creates every table of domain.md §3, v1.1 ones included', function (): void {
    $tables = [
        'tenants', 'users', 'customers', 'sites', 'materials', 'stocks', 'jobs', 'job_materials',
        'quotes', 'quote_lines', 'quote_line_materials', 'purchases', 'purchase_lines', 'stock_movements',
        'calendar_events', 'delivery_notes', 'delivery_note_lines', 'queue_jobs',
    ];

    foreach ($tables as $table) {
        expect(Schema::hasTable($table))->toBeTrue("missing table {$table}");
    }
});

it('gives tenants the defaults of domain.md §3.1', function (): void {
    $tenant = Tenant::query()->create(['name' => 'Ravizza Patrick'])->refresh();

    expect(Str::isUuid($tenant->id))->toBeTrue()
        ->and($tenant->tax_regime)->toBe(TaxRegime::Forfettario)
        ->and($tenant->default_vat_rate)->toBe('0.00')
        ->and($tenant->quote_validity_days)->toBe(DEFAULT_QUOTE_VALIDITY_DAYS)
        ->and($tenant->coverage_strategy)->toBe(CoverageStrategy::Avg)
        ->and($tenant->opened_package_ratio)->toBe('0.50')
        ->and($tenant->package_rounding)->toBe(PackageRounding::Up)
        ->and($tenant->trade)->toBe(Trade::Painter);
});

it('links the Belissi quote from tenant down to the line materials', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    $customer = Customer::factory()->for($tenant)->create(['name' => 'Condominio Belissi']);
    $site = Site::factory()->for($tenant)->for($customer)->create();
    $material = Material::factory()->for($tenant)->create();
    Stock::factory()->for($tenant)->for($material)->create(['closed_packages' => BELISSI_CLOSED_PACKAGES, 'opened_packages' => 1]);
    $job = Job::factory()->for($tenant)->create();
    JobMaterial::factory()->for($tenant)->for($job)->for($material)->create();
    $quote = Quote::factory()->for($tenant)->for($site)->create();
    $line = QuoteLine::factory()->for($tenant)->for($quote)->for($job)->create();
    QuoteLineMaterial::factory()->for($tenant)->for($line, 'line')->for($material)->create();

    $quote->refresh();
    $lineMaterial = $quote->lines->firstOrFail()->materials->firstOrFail();

    expect($user->tenant?->is($tenant))->toBeTrue()
        ->and($quote->customer?->is($customer))->toBeTrue()
        ->and($customer->quotes()->count())->toBe(1)
        ->and($quote->status)->toBe(QuoteStatus::Draft)
        ->and($quote->lines->firstOrFail()->unit)->toBe(JobUnit::SquareMeter)
        ->and($quote->lines->firstOrFail()->amount)->toBe('724.00')
        ->and($lineMaterial->required_quantity)->toBe('40.222')
        ->and($lineMaterial->required_packages)->toBe('3.000')
        ->and($lineMaterial->estimated_cost)->toBe('143.07')
        ->and($material->stock?->closed_packages)->toBe(BELISSI_CLOSED_PACKAGES)
        ->and($material->base_unit)->toBe(BaseUnit::Liter)
        ->and($job->jobMaterials->firstOrFail()->role)->toBe(JobMaterialRole::Primary)
        ->and($job->jobMaterials->firstOrFail()->material?->is($material))->toBeTrue();
});

it('records purchases and stock movements against materials', function (): void {
    $tenant = Tenant::factory()->create();
    $material = Material::factory()->for($tenant)->create();
    $purchase = Purchase::factory()->for($tenant)->create();
    PurchaseLine::factory()->for($tenant)->for($purchase)->for($material)->create();
    StockMovement::factory()->for($tenant)->for($material)->for($purchase)->create([
        'type' => StockMovementType::Purchase,
        'closed_delta' => 3,
    ]);

    expect($purchase->lines()->count())->toBe(1)
        ->and($purchase->paid)->toBeFalse()
        ->and($material->stockMovements->firstOrFail()->type)->toBe(StockMovementType::Purchase)
        ->and($material->stockMovements->firstOrFail()->purchase?->is($purchase))->toBeTrue();
});

it('keeps quote numbers unique per tenant and year', function (): void {
    $quote = Quote::factory()->create(['number' => 1, 'year' => 2026]);

    Quote::factory()->create(['tenant_id' => $quote->tenant_id, 'number' => 1, 'year' => 2026]);
})->throws(UniqueConstraintViolationException::class);
