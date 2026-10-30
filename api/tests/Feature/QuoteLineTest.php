<?php declare(strict_types=1);

use App\Enums\QuoteStatus;
use App\Models\Database\v1\JobMaterial;
use App\Models\Database\v1\Material;
use App\Models\Database\v1\Quote;
use App\Models\Database\v1\QuoteLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

// Belissi, domain.md §7.3
const BELISSI_SQM = 181;
const BELISSI_TOTAL = 974;
const BELISSI_COST = 143.07;
const BELISSI_MARGIN = 830.93;
const BELISSI_MARGIN_PCT = 85.3;
const BELISSI_PAINT_AMOUNT = 724;
const BELISSI_PAINT_PACKAGES = 3;
const BELISSI_PAINT_LITERS = 40.222;
const STUCCATURE_HOURS = 2;
const STUCCATURE_AMOUNT = 100;
const PORTONE_PRICE = 150;

// Tempera Wallcream price raised in the catalog after the line was saved
const NEW_TEMPERA_PRICE = 50;
const NEW_TEMPERA_COST = 150;

// Defaults and figures checked along the way (domain.md §7.1)
const DEFAULT_COATS = 2;
const AVG_COVERAGE = 9;
const OLD_TEMPERA_PRICE = 47.69;
const ONE_COAT_PACKAGES = 2;

// Preview: the saved line at 100 m² (200 / 9 = 22.2 lt → 2 bidoni) plus the portone
const PREVIEW_SQM = 100;
const PREVIEW_PAINT_AMOUNT = 400;
const PREVIEW_PAINT_PACKAGES = 2;
const PREVIEW_SUBTOTAL = 550;
const PREVIEW_COST = 95.38;

it('builds the real Belissi quote: 724 + 100 + 150 = 974.00 €, cost 143.07 €', function (): void {
    [, $quote] = belissiDraft();

    postJson("/api/quotes/{$quote->id}/lines", [
        'job_id' => seedJob('Imbiancatura interna')->id,
        'description' => 'Imbiancatura vano scala interno a due mani con pittura a tempera',
        'quantity' => BELISSI_SQM,
    ])
        ->assertStatus(Response::HTTP_CREATED)
        ->assertJsonPath('data.unit', 'sqm')
        ->assertJsonPath('data.coats', DEFAULT_COATS)
        ->assertJsonPath('data.amount', BELISSI_PAINT_AMOUNT)
        ->assertJsonPath('data.estimated_cost', BELISSI_COST)
        ->assertJsonPath('data.materials.0.material.name', 'Tempera Wallcream')
        ->assertJsonPath('data.materials.0.selected', true)
        ->assertJsonPath('data.materials.0.coverage_used', AVG_COVERAGE)
        ->assertJsonPath('data.materials.0.required_quantity', BELISSI_PAINT_LITERS)
        ->assertJsonPath('data.materials.0.required_packages', BELISSI_PAINT_PACKAGES)
        ->assertJsonPath('data.materials.1.selected', false)
        ->assertJsonPath('quote.subtotal', BELISSI_PAINT_AMOUNT);

    postJson("/api/quotes/{$quote->id}/lines", [
        'job_id' => seedJob('Stuccature')->id,
        'description' => 'Stuccature interne',
        'quantity' => STUCCATURE_HOURS,
    ])
        ->assertJsonPath('data.unit', 'hour')
        ->assertJsonPath('data.amount', STUCCATURE_AMOUNT)
        ->assertJsonPath('data.estimated_cost', 0);

    postJson("/api/quotes/{$quote->id}/lines", [
        'description' => 'Carteggiatura e tinteggiatura portone esterno con smalto in tinta',
        'quantity' => 1,
        'unit_price' => PORTONE_PRICE,
    ])
        ->assertJsonPath('data.job_id', null)
        ->assertJsonPath('data.unit', 'pc')
        ->assertJsonPath('data.materials', []);

    getJson("/api/quotes/{$quote->id}")
        ->assertJsonCount(BELISSI_PAINT_PACKAGES, 'data.lines')
        ->assertJsonPath('data.subtotal', BELISSI_TOTAL)
        ->assertJsonPath('data.total', BELISSI_TOTAL)
        ->assertJsonPath('data.estimated_cost', BELISSI_COST)
        ->assertJsonPath('data.estimated_margin', BELISSI_MARGIN)
        ->assertJsonPath('data.estimated_margin_pct', BELISSI_MARGIN_PCT)
        ->assertJsonPath('data.estimated_hours', STUCCATURE_HOURS)
        ->assertJsonPath('data.lines.2.description', 'Carteggiatura e tinteggiatura portone esterno con smalto in tinta');
});

it('refuses materials on free lines and a free line without description', function (): void {
    [$tenant, $quote] = belissiDraft();
    $material = Material::query()->where('tenant_id', $tenant->id)->firstOrFail();

    postJson("/api/quotes/{$quote->id}/lines", [
        'description' => 'Portone',
        'quantity' => 1,
        'unit_price' => PORTONE_PRICE,
        'materials' => [['material_id' => $material->id]],
    ])
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->assertJsonValidationErrors(['materials' => 'Le righe libere non hanno materiali.']);

    postJson("/api/quotes/{$quote->id}/lines", ['quantity' => 1, 'unit_price' => PORTONE_PRICE])
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->assertJsonValidationErrors(['description']);
});

it('ticks suggested materials and adds others by hand', function (): void {
    [, $quote] = belissiDraft();
    $fissativo = Material::query()->where('name', 'Fissativo Idrofis 14lt')->firstOrFail();
    $nastro = Material::query()->where('name', 'like', 'Nastro Carta%')->firstOrFail();
    $smalto = Material::query()->where('name', 'like', 'Sm. Opaco 2,5lt')->firstOrFail();

    postJson("/api/quotes/{$quote->id}/lines", [
        'job_id' => seedJob('Imbiancatura interna')->id,
        'quantity' => BELISSI_SQM,
        'materials' => [['material_id' => $nastro->id]],
    ])
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->assertJsonValidationErrors(['materials.0.consumption_per_unit']);

    postJson("/api/quotes/{$quote->id}/lines", [
        'job_id' => seedJob('Imbiancatura interna')->id,
        'quantity' => BELISSI_SQM,
        'materials' => [
            ['material_id' => $fissativo->id, 'selected' => true],
            ['material_id' => $smalto->id],
        ],
    ])
        ->assertStatus(Response::HTTP_CREATED)
        ->assertJsonPath('data.materials.1.material_id', $fissativo->id)
        ->assertJsonPath('data.materials.1.selected', true)
        ->assertJsonPath('data.materials.1.required_packages', 1)
        ->assertJsonPath('data.materials.3.material_id', $smalto->id)
        ->assertJsonPath('data.materials.3.role', 'secondary')
        ->assertJsonPath('data.materials.3.consumption_mode', 'coverage')
        ->assertJsonPath('data.materials.3.apply_coats', false);
});

it('keeps the snapshot on edit and takes the catalog on "ricalcola"', function (): void {
    [, $quote] = belissiDraft();
    $line = postJson("/api/quotes/{$quote->id}/lines", ['job_id' => seedJob('Imbiancatura interna')->id, 'quantity' => BELISSI_SQM]);
    $lineId = $line->json('data.id');
    Material::query()->where('name', 'Tempera Wallcream')->update(['cost_price' => NEW_TEMPERA_PRICE]);

    patchJson("/api/quotes/{$quote->id}/lines/{$lineId}", ['coats' => 1])
        ->assertStatus(Response::HTTP_OK)
        ->assertJsonPath('data.materials.0.required_packages', ONE_COAT_PACKAGES)
        ->assertJsonPath('data.materials.0.unit_cost', OLD_TEMPERA_PRICE);

    patchJson("/api/quotes/{$quote->id}/lines/{$lineId}", ['coats' => DEFAULT_COATS]);

    postJson("/api/quotes/{$quote->id}/calculate")
        ->assertStatus(Response::HTTP_OK)
        ->assertJsonPath('data.lines.0.materials.0.unit_cost', NEW_TEMPERA_PRICE)
        ->assertJsonPath('data.lines.0.materials.0.estimated_cost', NEW_TEMPERA_COST)
        ->assertJsonPath('data.estimated_cost', NEW_TEMPERA_COST)
        ->assertJsonPath('data.lines.0.amount', BELISSI_PAINT_AMOUNT);
});

it('realigns the consumption rule to the job on "ricalcola"', function (): void {
    [, $quote] = belissiDraft();
    $job = seedJob('Imbiancatura interna');
    postJson("/api/quotes/{$quote->id}/lines", ['job_id' => $job->id, 'quantity' => BELISSI_SQM]);
    JobMaterial::query()->where('job_id', $job->id)->where('role', 'primary')->update(['apply_coats' => false]);

    postJson("/api/quotes/{$quote->id}/calculate")
        ->assertJsonPath('data.lines.0.materials.0.apply_coats', false)
        ->assertJsonPath('data.lines.0.materials.0.required_packages', ONE_COAT_PACKAGES);
});

it('previews lines without saving, an existing line like its PATCH would', function (): void {
    [, $quote] = belissiDraft();
    $saved = postJson("/api/quotes/{$quote->id}/lines", ['job_id' => seedJob('Imbiancatura interna')->id, 'quantity' => BELISSI_SQM]);

    postJson("/api/quotes/{$quote->id}/preview-calculate", ['lines' => [
        ['id' => $saved->json('data.id'), 'quantity' => PREVIEW_SQM],
        ['description' => 'Portone', 'quantity' => 1, 'unit_price' => PORTONE_PRICE],
    ]])
        ->assertStatus(Response::HTTP_OK)
        ->assertJsonPath('data.lines.0.id', null)
        ->assertJsonPath('data.lines.0.amount', PREVIEW_PAINT_AMOUNT)
        ->assertJsonPath('data.lines.0.materials.0.required_packages', PREVIEW_PAINT_PACKAGES)
        ->assertJsonPath('data.totals.subtotal', PREVIEW_SUBTOTAL)
        ->assertJsonPath('data.totals.estimated_cost', PREVIEW_COST);

    expect(QuoteLine::query()->count())->toBe(1)
        ->and((float) $quote->refresh()->subtotal)->toBe((float) BELISSI_PAINT_AMOUNT);
});

it('deletes and reorders lines, keeping totals in sync', function (): void {
    [, $quote] = belissiDraft();
    $paint = postJson("/api/quotes/{$quote->id}/lines", ['job_id' => seedJob('Imbiancatura interna')->id, 'quantity' => BELISSI_SQM])->json('data.id');
    $door = postJson("/api/quotes/{$quote->id}/lines", ['description' => 'Portone', 'quantity' => 1, 'unit_price' => PORTONE_PRICE])->json('data.id');

    postJson("/api/quotes/{$quote->id}/lines/reorder", ['ids' => [$door, $paint]])->assertStatus(Response::HTTP_NO_CONTENT);
    getJson("/api/quotes/{$quote->id}")->assertJsonPath('data.lines.0.id', $door);

    deleteJson("/api/quotes/{$quote->id}/lines/{$paint}")->assertStatus(Response::HTTP_NO_CONTENT);
    getJson("/api/quotes/{$quote->id}")
        ->assertJsonPath('data.subtotal', PORTONE_PRICE)
        ->assertJsonPath('data.estimated_cost', 0);
});

it('brings a sent quote back to draft and locks accepted ones', function (): void {
    [, $quote] = belissiDraft();
    $quote->forceFill(['status' => QuoteStatus::Sent])->save();

    postJson("/api/quotes/{$quote->id}/lines", ['description' => 'Portone', 'quantity' => 1, 'unit_price' => PORTONE_PRICE])
        ->assertStatus(Response::HTTP_CREATED)
        ->assertJsonPath('quote.status', 'draft');

    $quote->forceFill(['status' => QuoteStatus::Accepted])->save();

    postJson("/api/quotes/{$quote->id}/lines", ['description' => 'Altro', 'quantity' => 1, 'unit_price' => PORTONE_PRICE])
        ->assertStatus(Response::HTTP_CONFLICT)
        ->assertJsonPath('message', 'Il preventivo è accettato: righe e materiali non si possono più cambiare.');
    postJson("/api/quotes/{$quote->id}/calculate")->assertStatus(Response::HTTP_CONFLICT);
});

it('does not reach lines of another quote', function (): void {
    [$tenant, $quote] = belissiDraft();
    $other = Quote::factory()->for($tenant)->create();
    $foreignLine = QuoteLine::factory()->for($tenant)->for($other)->create();

    patchJson("/api/quotes/{$quote->id}/lines/{$foreignLine->id}", ['quantity' => 1])->assertStatus(Response::HTTP_NOT_FOUND);
    deleteJson("/api/quotes/{$quote->id}/lines/{$foreignLine->id}")->assertStatus(Response::HTTP_NOT_FOUND);
});
