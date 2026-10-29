<?php declare(strict_types=1);

use App\Models\Database\v1\Customer;
use App\Models\Database\v1\Tenant;
use App\Models\Database\v1\User;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\withToken;

uses(RefreshDatabase::class);

it('logs in, reads me and logs out with a Bearer token', function (): void {
    $tenant = Tenant::factory()->create(['name' => 'Ravizza Patrick']);
    User::factory()->for($tenant)->create(['email' => 'patrick@example.com', 'password' => 'segreta123']);

    $login = postJson('/api/auth/login', ['email' => 'Patrick@Example.com', 'password' => 'segreta123'])
        ->assertStatus(Response::HTTP_OK)
        ->assertJsonPath('data.user.email', 'patrick@example.com')
        ->assertJsonPath('data.user.tenant.name', 'Ravizza Patrick')
        ->assertJsonPath('data.user.tenant.coverage_strategy', 'avg');

    $token = (string) $login->json('data.token');

    withToken($token)->getJson('/api/auth/me')
        ->assertStatus(Response::HTTP_OK)
        ->assertJsonPath('data.email', 'patrick@example.com')
        ->assertJsonPath('data.tenant.id', $tenant->id);

    withToken($token)->postJson('/api/auth/logout')
        ->assertStatus(Response::HTTP_NO_CONTENT);

    assertDatabaseCount('personal_access_tokens', 0);
});

it('rejects wrong credentials with a 422 on email', function (): void {
    User::factory()->create(['email' => 'patrick@example.com', 'password' => 'segreta123']);

    postJson('/api/auth/login', ['email' => 'patrick@example.com', 'password' => 'sbagliata'])
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->assertJsonValidationErrors(['email' => 'Email o password non corretti.']);
});

it('answers 401 JSON without a token, even without an Accept header', function (): void {
    getJson('/api/auth/me')->assertStatus(Response::HTTP_UNAUTHORIZED);

    get('/api/auth/me')->assertStatus(Response::HTTP_UNAUTHORIZED);
});

it('scopes domain queries to the tenant of the authenticated user', function (): void {
    $mine = Tenant::factory()->create();
    $other = Tenant::factory()->create();
    Customer::factory()->for($mine)->create(['name' => 'Condominio Belissi']);
    Customer::factory()->for($other)->create(['name' => 'Cliente di un altro']);

    // Nobody authenticated: no filter
    expect(Customer::query()->count())->toBe(Tenant::query()->count());

    Sanctum::actingAs(User::factory()->for($mine)->create());

    expect(Customer::query()->pluck('name')->all())->toBe(['Condominio Belissi']);
});

it('fills tenant_id on new records from the authenticated user', function (): void {
    $user = User::factory()->create();
    actingAs($user);

    $customer = Customer::query()->create(['name' => 'Rossi Mario']);

    expect($customer->tenant_id)->toBe($user->tenant_id);
});

it('creates a tenant and its user from the console', function (): void {
    $exitCode = Artisan::call('app:create-tenant', [
        'name' => 'Ravizza Patrick',
        'email' => 'Patrick@Example.com',
        '--user-name' => 'Patrick',
        '--password' => 'segreta123',
    ]);

    expect($exitCode)->toBe(Command::SUCCESS);

    assertDatabaseHas('tenants', ['name' => 'Ravizza Patrick']);
    assertDatabaseHas('users', ['email' => 'patrick@example.com', 'name' => 'Patrick']);

    postJson('/api/auth/login', ['email' => 'patrick@example.com', 'password' => 'segreta123'])
        ->assertStatus(Response::HTTP_OK);
});

it('refuses a duplicate email from the console', function (): void {
    User::factory()->create(['email' => 'patrick@example.com']);

    $exitCode = Artisan::call('app:create-tenant', [
        'name' => 'Ravizza Patrick',
        'email' => 'patrick@example.com',
        '--password' => 'segreta123',
    ]);

    expect($exitCode)->toBe(Command::FAILURE);

    assertDatabaseCount('tenants', 1);
});
