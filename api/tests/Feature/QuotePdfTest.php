<?php declare(strict_types=1);

use App\Services\Quotes\QuotePdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('serves the PDF inline with the quote filename', function (): void {
    $quote = belissiQuote();

    $response = get("/api/quotes/{$quote->id}/pdf")
        ->assertStatus(Response::HTTP_OK)
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename="Preventivo_1_2026_Condominio_Belissi.pdf"');

    expect($response->getContent())->toStartWith('%PDF-');
});

it('prints the numbers of the Belissi quote and nothing internal', function (): void {
    $html = app(QuotePdf::class)->html(belissiQuote());

    expect($html)
        ->toContain('Ravizza Patrick')
        ->toContain('Preventivo nr')
        ->toContain('10/01/2026')
        ->toContain('Condominio Belissi')
        ->toContain('Vano scala')
        ->toContain('38067 Ledro (TN)')
        ->toContain('181 mq')
        ->toContain('2 ore')
        ->toContain('1 unità')
        ->toContain('724,00')
        ->toContain('100,00')
        ->toContain('150,00')
        ->toContain('974,00 €')
        ->toContain('+ Imposta IVA (0%)')
        ->toContain('Rimessa diretta')
        ->toContain('Operazione senza applicazione Iva, regime forfettario')
        ->toContain('Il presente documento scadrà il 10/04/2026.');

    // Never materials, costs or private notes (domain.md §5.8)
    expect(str_contains($html, 'Tempera Wallcream'))->toBeFalse()
        ->and(str_contains($html, '143,07'))->toBeFalse()
        ->and(str_contains($html, 'Chiavi dal portinaio'))->toBeFalse();
});
