<?php declare(strict_types=1);

use App\Enums\ConsumptionMode;
use App\Enums\JobMaterialRole;
use App\Models\Database\v1\Job;
use App\Models\Database\v1\Material;
use App\Models\Database\v1\Stock;
use App\Models\Database\v1\Tenant;
use App\Models\Database\v1\User;
use Database\Seeders\PainterCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Laravel\Sanctum\Sanctum;
use function Pest\Laravel\postJson;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

// docs/seed: 72 materials, 18 jobs of which 7 active in v1 (domain.md §8.1)
const SEED_MATERIALS = 72;
const SEED_JOBS = 18;
const SEED_ACTIVE_JOBS = 7;

// Imbiancatura interna: 2 coats by default
const INTERIOR_DEFAULT_COATS = 2;

// Painter tenants in the console test
const PAINTER_TENANTS = 2;

it('loads the painter catalog for the tenant at first start', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    postJson('/api/tenant/seed-catalog')
        ->assertStatus(Response::HTTP_CREATED)
        ->assertJsonPath('data.jobs', SEED_JOBS)
        ->assertJsonPath('data.materials', SEED_MATERIALS);

    $activeJobs = Job::query()->where('active', true)->with('jobMaterials.material')->get();

    expect($activeJobs)->toHaveCount(SEED_ACTIVE_JOBS)
        ->and(Material::query()->count())->toBe(SEED_MATERIALS)
        ->and(Stock::query()->count())->toBe(SEED_MATERIALS)
        ->and(Stock::query()->where('closed_packages', '>', 0)->orWhere('opened_packages', '>', 0)->exists())->toBeFalse();

    $interior = $activeJobs->firstWhere('name', 'Imbiancatura interna');
    $primary = $interior?->jobMaterials->firstOrFail();

    expect($interior?->unit_price)->toBe('4.00')
        ->and($interior?->default_coats)->toBe(INTERIOR_DEFAULT_COATS)
        ->and($primary?->material?->name)->toBe('Tempera Wallcream')
        ->and($primary?->material?->cost_price)->toBe('47.69')
        ->and($primary?->role)->toBe(JobMaterialRole::Primary)
        ->and($primary?->consumption_mode)->toBe(ConsumptionMode::Coverage)
        ->and($primary?->apply_coats)->toBeTrue()
        ->and($primary?->selected_by_default)->toBeTrue()
        ->and($interior?->jobMaterials->where('role', JobMaterialRole::Checklist)->isNotEmpty())->toBeTrue();
});

it('refuses to seed a catalog that is not empty with a 409', function (): void {
    Sanctum::actingAs(User::factory()->create());

    postJson('/api/tenant/seed-catalog')->assertStatus(Response::HTTP_CREATED);
    postJson('/api/tenant/seed-catalog')->assertStatus(Response::HTTP_CONFLICT);

    expect(Material::query()->count())->toBe(SEED_MATERIALS);
});

it('seeds only the authenticated tenant', function (): void {
    $other = Tenant::factory()->create();
    Sanctum::actingAs(User::factory()->create());

    postJson('/api/tenant/seed-catalog')->assertStatus(Response::HTTP_CREATED);

    expect(Material::withoutGlobalScopes()->whereBelongsTo($other)->exists())->toBeFalse();
});

it('seeds every empty painter tenant from the console, once', function (): void {
    Tenant::factory()->count(PAINTER_TENANTS)->create();

    seed(PainterCatalogSeeder::class);
    seed(PainterCatalogSeeder::class);

    expect(Material::query()->count())->toBe(SEED_MATERIALS * PAINTER_TENANTS)
        ->and(Job::query()->where('active', true)->count())->toBe(SEED_ACTIVE_JOBS * PAINTER_TENANTS);
});
