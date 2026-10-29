<?php declare(strict_types=1);

use App\Models\Database\v1\Job;
use App\Models\Database\v1\Material;
use App\Models\Database\v1\QuoteLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

// docs/seed: 7 active jobs in v1 (domain.md §8.1)
const ACTIVE_SEED_JOBS = 7;

// Imbiancatura interna at 4,00 €/mq with Tempera Wallcream at 47,69 € (domain.md §7.3)
const SEED_INTERIOR_PRICE = 4;
const SEED_WALLCREAM_COST = 47.69;

it('lists the 7 active seed jobs with their suggested materials', function (): void {
    signInTenant();
    postJson('/api/tenant/seed-catalog')->assertStatus(Response::HTTP_CREATED);

    getJson('/api/jobs?active=1')
        ->assertStatus(Response::HTTP_OK)
        ->assertJsonPath('meta.total', ACTIVE_SEED_JOBS)
        ->assertJsonPath('data.0.name', 'Imbiancatura interna')
        ->assertJsonPath('data.0.unit', 'sqm')
        ->assertJsonPath('data.0.unit_price', SEED_INTERIOR_PRICE)
        ->assertJsonPath('data.0.materials.0.role', 'primary')
        ->assertJsonPath('data.0.materials.0.material.name', 'Tempera Wallcream')
        ->assertJsonPath('data.0.materials.0.material.cost_price', SEED_WALLCREAM_COST);
});

it('creates a job with its suggested materials inline, in order', function (): void {
    $tenant = signInTenant();
    $paint = Material::factory()->for($tenant)->create(['name' => 'Tempera Wallcream']);
    $filler = Material::factory()->for($tenant)->create(['name' => 'Stucco gesso']);

    $payload = [
        'name' => 'Tinteggiatura soffitti',
        'unit' => 'sqm',
        'unit_price' => 5,
        'default_coats' => 2,
        'materials' => [
            ['material_id' => $paint->id, 'role' => 'primary', 'consumption_mode' => 'coverage', 'apply_coats' => true, 'selected_by_default' => true],
            ['material_id' => $filler->id, 'role' => 'secondary', 'consumption_mode' => 'per_unit', 'consumption_per_unit' => 0.05],
        ],
    ];

    postJson('/api/jobs', $payload)
        ->assertStatus(Response::HTTP_CREATED)
        ->assertJsonPath('data.name', 'Tinteggiatura soffitti')
        ->assertJsonPath('data.active', true)
        ->assertJsonPath('data.materials.0.material_id', $paint->id)
        ->assertJsonPath('data.materials.1.consumption_per_unit', $payload['materials'][1]['consumption_per_unit'])
        ->assertJsonPath('data.materials.1.sort_order', 1);
});

it('validates the consumption rule and refuses materials of other tenants', function (): void {
    $tenant = signInTenant();
    $mine = Material::factory()->for($tenant)->create();
    $foreign = Material::factory()->create();

    postJson('/api/jobs', [
        'name' => 'Rasatura',
        'unit' => 'sqm',
        'unit_price' => 8.5,
        'materials' => [
            ['material_id' => $mine->id, 'role' => 'primary', 'consumption_mode' => 'per_unit'],
            ['material_id' => $foreign->id, 'role' => 'secondary', 'consumption_mode' => 'coverage'],
        ],
    ])
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->assertJsonValidationErrors(['materials.0.consumption_per_unit', 'materials.1.material_id']);
});

it('replaces the materials only when the list is sent', function (): void {
    $tenant = signInTenant();
    $job = Job::factory()->for($tenant)->create();
    $old = Material::factory()->for($tenant)->create();
    $new = Material::factory()->for($tenant)->create();
    $job->jobMaterials()->create(['tenant_id' => $tenant->id, 'material_id' => $old->id, 'role' => 'primary', 'consumption_mode' => 'coverage']);

    $patch = ['unit_price' => 4.5];

    patchJson("/api/jobs/{$job->id}", $patch)
        ->assertStatus(Response::HTTP_OK)
        ->assertJsonPath('data.unit_price', $patch['unit_price'])
        ->assertJsonPath('data.materials.0.material_id', $old->id);

    patchJson("/api/jobs/{$job->id}", ['materials' => [
        ['material_id' => $new->id, 'role' => 'primary', 'consumption_mode' => 'coverage'],
    ]])
        ->assertStatus(Response::HTTP_OK)
        ->assertJsonCount(1, 'data.materials')
        ->assertJsonPath('data.materials.0.material_id', $new->id);
});

it('deletes an unused job and only deactivates a job used in quotes', function (): void {
    $tenant = signInTenant();
    $unused = Job::factory()->for($tenant)->create();
    $used = Job::factory()->for($tenant)->create();
    QuoteLine::factory()->for($tenant)->for($used)->create();

    deleteJson("/api/jobs/{$unused->id}")->assertStatus(Response::HTTP_NO_CONTENT);
    deleteJson("/api/jobs/{$used->id}")
        ->assertStatus(Response::HTTP_OK)
        ->assertJsonPath('data.active', false);

    expect(Job::query()->find($unused->id))->toBeNull()
        ->and(Job::query()->find($used->id))->not->toBeNull();
});

it('reorders jobs as the array says', function (): void {
    $tenant = signInTenant();
    $first = Job::factory()->for($tenant)->create(['name' => 'Prima', 'sort_order' => 0]);
    $second = Job::factory()->for($tenant)->create(['name' => 'Seconda', 'sort_order' => 1]);

    postJson('/api/jobs/reorder', ['ids' => [$second->id, $first->id]])->assertStatus(Response::HTTP_NO_CONTENT);

    getJson('/api/jobs')
        ->assertJsonPath('data.0.name', 'Seconda')
        ->assertJsonPath('data.1.name', 'Prima');
});

it('hides jobs of other tenants', function (): void {
    signInTenant();
    $foreign = Job::factory()->create();

    getJson("/api/jobs/{$foreign->id}")->assertStatus(Response::HTTP_NOT_FOUND);
    postJson('/api/jobs/reorder', ['ids' => [$foreign->id]])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
});
