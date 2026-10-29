<?php declare(strict_types=1);

use App\Models\Database\v1\Customer;
use App\Models\Database\v1\Quote;
use App\Models\Database\v1\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

it('creates and lists the sites of a customer', function (): void {
    $tenant = signInTenant();
    $customer = Customer::factory()->for($tenant)->create(['name' => 'Condominio Belissi']);

    postJson("/api/customers/{$customer->id}/sites", ['name' => 'Vano scala'])
        ->assertStatus(Response::HTTP_CREATED)
        ->assertJsonPath('data.name', 'Vano scala')
        ->assertJsonPath('data.customer_id', $customer->id)
        ->assertJsonPath('data.address', null);

    postJson("/api/customers/{$customer->id}/sites", ['name' => 'Facciata cortile', 'address' => 'Via Roma 12'])
        ->assertStatus(Response::HTTP_CREATED);

    getJson("/api/customers/{$customer->id}/sites")
        ->assertStatus(Response::HTTP_OK)
        ->assertJsonPath('data.0.name', 'Facciata cortile')
        ->assertJsonPath('data.1.name', 'Vano scala');

    expect(Site::query()->where('tenant_id', $tenant->id)->count())->toBe(Site::query()->count());
});

it('requires the site name', function (): void {
    $tenant = signInTenant();
    $customer = Customer::factory()->for($tenant)->create(['name' => 'Condominio Belissi']);

    postJson("/api/customers/{$customer->id}/sites", [])
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->assertJsonValidationErrors(['name' => 'Il campo nome è obbligatorio.']);
});

it('updates and deletes a site', function (): void {
    $tenant = signInTenant();
    $customer = Customer::factory()->for($tenant)->create(['name' => 'Condominio Belissi']);

    $site = Site::factory()->for($tenant)->for($customer)->create(['name' => 'Vano scala']);

    patchJson("/api/sites/{$site->id}", ['city' => 'Bergamo'])
        ->assertStatus(Response::HTTP_OK)
        ->assertJsonPath('data.city', 'Bergamo')
        ->assertJsonPath('data.name', 'Vano scala');

    deleteJson("/api/sites/{$site->id}")->assertStatus(Response::HTTP_NO_CONTENT);
});

it('refuses to delete a site with quotes', function (): void {
    $tenant = signInTenant();
    $customer = Customer::factory()->for($tenant)->create(['name' => 'Condominio Belissi']);

    $site = Site::factory()->for($tenant)->for($customer)->create();
    Quote::factory()->for($tenant)->for($site)->create();

    deleteJson("/api/sites/{$site->id}")
        ->assertStatus(Response::HTTP_CONFLICT)
        ->assertJsonPath('message', 'Il cantiere ha dei preventivi: non si può eliminare.');
});

it('does not touch sites or customers of other tenants', function (): void {
    signInTenant();

    $foreignCustomer = Customer::factory()->create();
    $foreignSite = Site::factory()->create();

    postJson("/api/customers/{$foreignCustomer->id}/sites", ['name' => 'X'])->assertStatus(Response::HTTP_NOT_FOUND);
    patchJson("/api/sites/{$foreignSite->id}", ['name' => 'X'])->assertStatus(Response::HTTP_NOT_FOUND);
    deleteJson("/api/sites/{$foreignSite->id}")->assertStatus(Response::HTTP_NOT_FOUND);
});
