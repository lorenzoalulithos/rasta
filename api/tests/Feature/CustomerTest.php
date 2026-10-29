<?php declare(strict_types=1);

use App\Models\Database\v1\Customer;
use App\Models\Database\v1\Quote;
use App\Models\Database\v1\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

// Customers of the authenticated tenant in the list test
const OWN_CUSTOMERS = 2;

it('lists customers by name, searchable, with counts and pagination', function (): void {
    $tenant = signInTenant();

    $belissi = Customer::factory()->for($tenant)->create(['name' => 'Condominio Belissi', 'city' => 'Bergamo']);
    Customer::factory()->for($tenant)->create(['name' => 'Rossi Mario', 'city' => 'Seriate']);
    Site::factory()->for($tenant)->for($belissi)->create();
    Customer::factory()->create(['name' => 'Cliente di un altro']);

    getJson('/api/customers')
        ->assertStatus(Response::HTTP_OK)
        ->assertJsonPath('meta.total', OWN_CUSTOMERS)
        ->assertJsonPath('data.0.name', 'Condominio Belissi')
        ->assertJsonPath('data.0.sites_count', 1)
        ->assertJsonPath('data.0.quotes_count', 0);

    getJson('/api/customers?search=seriate')
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.name', 'Rossi Mario');
});

it('creates a customer with only the name, in the user tenant', function (): void {
    $tenant = signInTenant();

    postJson('/api/customers', ['name' => 'Ferrari Giulia'])
        ->assertStatus(Response::HTTP_CREATED)
        ->assertJsonPath('data.name', 'Ferrari Giulia')
        ->assertJsonPath('data.city', null);

    expect(Customer::query()->sole()->tenant_id)->toBe($tenant->id);
});

it('answers validation errors in Italian', function (): void {
    signInTenant();

    postJson('/api/customers', ['email' => 'non-una-email'])
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->assertJsonValidationErrors([
            'name' => 'Il campo nome è obbligatorio.',
            'email' => "L'email non sembra giusta.",
        ]);
});

it('shows a customer with its sites and updates it partially', function (): void {
    $tenant = signInTenant();

    $customer = Customer::factory()->for($tenant)->create(['name' => 'Condominio Belissi', 'city' => 'Bergamo']);
    Site::factory()->for($tenant)->for($customer)->create(['name' => 'Vano scala']);

    getJson("/api/customers/{$customer->id}")
        ->assertStatus(Response::HTTP_OK)
        ->assertJsonPath('data.sites.0.name', 'Vano scala');

    patchJson("/api/customers/{$customer->id}", ['phone' => '035 123456'])
        ->assertStatus(Response::HTTP_OK)
        ->assertJsonPath('data.phone', '035 123456')
        ->assertJsonPath('data.city', 'Bergamo');
});

it('deletes a customer without quotes together with its sites', function (): void {
    $tenant = signInTenant();

    $customer = Customer::factory()->for($tenant)->create();
    $site = Site::factory()->for($tenant)->for($customer)->create();

    deleteJson("/api/customers/{$customer->id}")->assertStatus(Response::HTTP_NO_CONTENT);

    assertDatabaseMissing('sites', ['id' => $site->id]);
});

it('refuses to delete a customer with quotes', function (): void {
    $tenant = signInTenant();

    $customer = Customer::factory()->for($tenant)->create();
    $site = Site::factory()->for($tenant)->for($customer)->create();
    Quote::factory()->for($tenant)->for($site)->create();

    deleteJson("/api/customers/{$customer->id}")
        ->assertStatus(Response::HTTP_CONFLICT)
        ->assertJsonPath('message', 'Il cliente ha dei preventivi: non si può eliminare.');
});

it('hides customers of other tenants', function (): void {
    signInTenant();

    $other = Customer::factory()->create();

    getJson("/api/customers/{$other->id}")->assertStatus(Response::HTTP_NOT_FOUND);
    patchJson("/api/customers/{$other->id}", ['name' => 'X'])->assertStatus(Response::HTTP_NOT_FOUND);
    deleteJson("/api/customers/{$other->id}")->assertStatus(Response::HTTP_NOT_FOUND);
});
