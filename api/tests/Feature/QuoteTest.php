<?php declare(strict_types=1);

use App\Enums\QuoteStatus;
use App\Models\Database\v1\Customer;
use App\Models\Database\v1\Quote;
use App\Models\Database\v1\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

// Tenant settings copied on the new quote (domain.md §3.1)
const QUOTE_VALIDITY_DAYS = 90;
const ORDINARY_VAT = 22;

// 974.00 € with VAT 22%
const ORDINARY_VAT_AMOUNT = 214.28;
const ORDINARY_TOTAL = 1188.28;

// Year of the created quote
const QUOTE_YEAR = 2026;

// Two quotes of the tenant in the list test
const OWN_QUOTES = 2;

it('creates a draft that copies VAT, footer, payment terms and validity from the tenant', function (): void {
    Carbon::setTestNow('2026-01-10 09:00:00');
    $tenant = signInTenant([
        'default_vat_rate' => ORDINARY_VAT,
        'quote_validity_days' => QUOTE_VALIDITY_DAYS,
        'quote_footer_text' => 'Operazione in franchigia da IVA',
        'default_payment_terms' => 'Rimessa diretta',
    ]);
    $customer = Customer::factory()->for($tenant)->create(['name' => 'Condominio Belissi']);
    $site = Site::factory()->for($tenant)->for($customer)->create(['name' => 'Vano scala']);

    postJson('/api/quotes', ['site_id' => $site->id])
        ->assertStatus(Response::HTTP_CREATED)
        ->assertJsonPath('data.number', 1)
        ->assertJsonPath('data.year', QUOTE_YEAR)
        ->assertJsonPath('data.number_label', '1/2026')
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.issued_at', '2026-01-10')
        ->assertJsonPath('data.expires_at', '2026-04-10')
        ->assertJsonPath('data.vat_rate', ORDINARY_VAT)
        ->assertJsonPath('data.footer_text', 'Operazione in franchigia da IVA')
        ->assertJsonPath('data.payment_terms', 'Rimessa diretta')
        ->assertJsonPath('data.customer.name', 'Condominio Belissi')
        ->assertJsonPath('data.site.name', 'Vano scala')
        ->assertJsonPath('data.lines', [])
        ->assertJsonPath('data.total', 0)
        ->assertJsonPath('data.estimated_margin_pct', null);
});

it('refuses a site of another tenant', function (): void {
    signInTenant();
    $foreign = Site::factory()->create();

    postJson('/api/quotes', ['site_id' => $foreign->id])
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->assertJsonValidationErrors(['site_id']);
});

it('lists quotes newest first, by status and by customer, site or number', function (): void {
    $tenant = signInTenant();
    $belissi = Site::factory()->for($tenant)->for(Customer::factory()->for($tenant)->state(['name' => 'Condominio Belissi']))->create();
    $rossi = Site::factory()->for($tenant)->for(Customer::factory()->for($tenant)->state(['name' => 'Rossi Mario']))->create();
    $first = Quote::factory()->for($tenant)->for($belissi)->create(['number' => 1, 'status' => QuoteStatus::Accepted]);
    $second = Quote::factory()->for($tenant)->for($rossi)->create(['number' => 2]);
    Quote::factory()->create();

    getJson('/api/quotes')
        ->assertJsonPath('meta.total', OWN_QUOTES)
        ->assertJsonPath('data.0.id', $second->id)
        ->assertJsonPath('data.0.customer.name', 'Rossi Mario');

    getJson('/api/quotes?status=accepted')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $first->id);
    getJson('/api/quotes?search=belissi')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $first->id);
    getJson('/api/quotes?search=2')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $second->id);
});

it('edits a draft, recomputing expiry and VAT', function (): void {
    $tenant = signInTenant(['quote_validity_days' => QUOTE_VALIDITY_DAYS]);
    $quote = Quote::factory()->for($tenant)->create(['subtotal' => '974.00', 'total' => '974.00']);

    patchJson("/api/quotes/{$quote->id}", ['issued_at' => '2026-02-01', 'vat_rate' => ORDINARY_VAT, 'payment_terms' => '30 giorni'])
        ->assertStatus(Response::HTTP_OK)
        ->assertJsonPath('data.issued_at', '2026-02-01')
        ->assertJsonPath('data.expires_at', '2026-05-02')
        ->assertJsonPath('data.vat_amount', ORDINARY_VAT_AMOUNT)
        ->assertJsonPath('data.total', ORDINARY_TOTAL)
        ->assertJsonPath('data.payment_terms', '30 giorni')
        ->assertJsonPath('data.status', 'draft');
});

it('brings a sent quote back to draft when the document changes', function (): void {
    $tenant = signInTenant();
    $quote = Quote::factory()->for($tenant)->create(['status' => QuoteStatus::Sent]);

    patchJson("/api/quotes/{$quote->id}", ['private_notes' => 'Chiavi dal portinaio'])->assertJsonPath('data.status', 'sent');
    patchJson("/api/quotes/{$quote->id}", ['payment_terms' => 'Bonifico'])->assertJsonPath('data.status', 'draft');
});

it('allows only private notes on accepted, rejected and completed quotes', function (): void {
    $tenant = signInTenant();
    $quote = Quote::factory()->for($tenant)->create(['status' => QuoteStatus::Accepted]);

    patchJson("/api/quotes/{$quote->id}", ['private_notes' => 'Pagato in contanti'])
        ->assertStatus(Response::HTTP_OK)
        ->assertJsonPath('data.private_notes', 'Pagato in contanti');

    patchJson("/api/quotes/{$quote->id}", ['vat_rate' => ORDINARY_VAT])
        ->assertStatus(Response::HTTP_CONFLICT)
        ->assertJsonPath('message', 'Il preventivo è accettato: si possono cambiare solo le note private.');
});

it('deletes only drafts', function (): void {
    $tenant = signInTenant();
    $draft = Quote::factory()->for($tenant)->create();
    $sent = Quote::factory()->for($tenant)->create(['status' => QuoteStatus::Sent]);

    deleteJson("/api/quotes/{$draft->id}")->assertStatus(Response::HTTP_NO_CONTENT);
    deleteJson("/api/quotes/{$sent->id}")
        ->assertStatus(Response::HTTP_CONFLICT)
        ->assertJsonPath('message', 'Il preventivo è inviato: si eliminano solo le bozze.');
});

it('hides quotes of other tenants', function (): void {
    signInTenant();
    $foreign = Quote::factory()->create();

    getJson("/api/quotes/{$foreign->id}")->assertStatus(Response::HTTP_NOT_FOUND);
    patchJson("/api/quotes/{$foreign->id}", ['private_notes' => 'x'])->assertStatus(Response::HTTP_NOT_FOUND);
    deleteJson("/api/quotes/{$foreign->id}")->assertStatus(Response::HTTP_NOT_FOUND);
});
