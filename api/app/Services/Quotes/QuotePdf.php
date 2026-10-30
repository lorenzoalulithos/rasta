<?php declare(strict_types=1);

namespace App\Services\Quotes;

use App\Enums\JobUnit;
use App\Models\Database\v1\Customer;
use App\Models\Database\v1\Quote;
use App\Models\Database\v1\QuoteLine;
use App\Models\Database\v1\Site;
use App\Models\Database\v1\Tenant;
use App\Services\Calculator\Decimal;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

/**
 * The quote PDF, structure of the partner's quote (domain.md §5.8).
 *
 * Never materials, costs, margin or private notes.
 */
final class QuotePdf
{
    /**
     * Up to this many lines the summary box is pinned to the bottom of the page.
     *
     * @var int
     */
    private const LINES_WITH_SUMMARY_AT_BOTTOM = 8;

    /**
     * @param Quote $quote
     *
     * @return string PDF bytes
     */
    public function render(Quote $quote): string
    {
        // Font subsetting: only the glyphs used, a light PDF to share on WhatsApp
        return Pdf::loadHTML($this->html($quote))
                  ->setPaper('a4')
                  ->setOption('isFontSubsettingEnabled', true)
                  ->output();
    }

    /**
     * The HTML the PDF is made from: also what the tests read.
     *
     * @param Quote $quote
     *
     * @return string
     */
    public function html(Quote $quote): string
    {
        $quote->loadMissing(['tenant', 'site.customer', 'lines']);

        /** @var Tenant $tenant */
        $tenant = $quote->tenant;

        /** @var Site $site */
        $site = $quote->site;

        /** @var Customer $customer */
        $customer = $site->customer;

        return view('pdf.quote', [
            'tenant' => [
                'name' => $tenant->name,
                'address' => self::address($tenant->address, $tenant->zip, $tenant->city, $tenant->province),
                'fiscal' => self::fiscal($tenant->vat_number, $tenant->tax_code),
                'contacts' => implode(' · ', array_filter([$tenant->phone, $tenant->email])),
            ],
            'number' => $quote->number,
            'date' => $quote->issued_at->format('d/m/Y'),
            'recipient' => [
                'name' => $customer->name,
                'address' => self::address($customer->address, $customer->zip, $customer->city, $customer->province),
                'fiscal' => self::fiscal($customer->vat_number, $customer->tax_code),
            ],
            'destination' => [
                'name' => $customer->name,
                'site' => $site->name,
                'address' => null !== $site->address
                    ? self::address($site->address, $site->zip, $site->city, $site->province)
                    : self::address($customer->address, $customer->zip, $customer->city, $customer->province),
            ],
            'lines' => $quote->lines->map(static fn (QuoteLine $line): array => [
                'description' => $line->description,
                'quantity' => self::quantity($line->quantity, $line->unit),
                'unit_price' => self::money($line->unit_price),
                'amount' => self::money($line->amount),
            ])->all(),
            'footer_text' => $quote->footer_text,
            'expires_at' => $quote->expires_at->format('d/m/Y'),
            'payment_terms' => $quote->payment_terms,
            'subtotal' => self::money($quote->subtotal),
            'vat_rate' => self::number($quote->vat_rate),
            'vat_amount' => self::money($quote->vat_amount),
            'total' => self::money($quote->total),
            'linesAtBottom' => self::LINES_WITH_SUMMARY_AT_BOTTOM,
        ])->render();
    }

    /**
     * Preventivo_1_2026_Condominio_Belissi.pdf.
     *
     * @param Quote $quote
     *
     * @return string
     */
    public function filename(Quote $quote): string
    {
        $quote->loadMissing('site.customer');
        $customer = Str::of((string) $quote->site?->customer?->name)->ascii()->replaceMatches('/[^A-Za-z0-9]+/', '_')->trim('_');

        return "Preventivo_{$quote->number}_{$quote->year}" . ($customer->isEmpty() ? '' : "_{$customer}") . '.pdf';
    }

    /**
     * "4,00".
     *
     * @param float|string $value
     *
     * @return string
     */
    private static function money(float|string $value): string
    {
        return number_format((float) $value, Decimal::MONEY, ',', '.');
    }

    /**
     * "181", "2,5", "22": no useless zeros.
     *
     * @param float|string $value
     *
     * @return string
     */
    private static function number(float|string $value): string
    {
        // "181,00" → "181", "2,50" → "2,5", "0,00" → "0"
        return (string) preg_replace('/,?0+$/', '', number_format((float) $value, Decimal::MONEY, ',', '.'));
    }

    /**
     * "181 mq", "2 ore", "1 ora", "1 unità".
     *
     * @param float|string $quantity
     * @param JobUnit      $unit
     *
     * @return string
     */
    private static function quantity(float|string $quantity, JobUnit $unit): string
    {
        $one = 0 === bccomp(Decimal::of($quantity), '1', Decimal::QUANTITY);

        $label = match ($unit) {
            JobUnit::SquareMeter => 'mq',
            JobUnit::Hour => $one ? 'ora' : 'ore',
            JobUnit::Piece => 'unità',
            JobUnit::Pair => $one ? 'coppia' : 'coppie',
            JobUnit::LinearMeter => 'ml',
        };

        return self::number($quantity) . ' ' . $label;
    }

    /**
     * Address lines: street, then "zip city (province)".
     *
     * @param string|null $street
     * @param string|null $zip
     * @param string|null $city
     * @param string|null $province
     *
     * @return array<int, string>
     */
    private static function address(?string $street, ?string $zip, ?string $city, ?string $province): array
    {
        $place = mb_trim(implode(' ', array_filter([$zip, $city])) . (null !== $province && '' !== $province ? " ({$province})" : ''));

        return array_values(array_filter([$street, $place], static fn (?string $part): bool => null !== $part && '' !== $part));
    }

    /**
     * "Partita IVA … Cod. Fiscale …", only what is known.
     *
     * @param string|null $vatNumber
     * @param string|null $taxCode
     *
     * @return string
     */
    private static function fiscal(?string $vatNumber, ?string $taxCode): string
    {
        return implode('   ', array_filter([
            null !== $vatNumber && '' !== $vatNumber ? "Partita IVA {$vatNumber}" : null,
            null !== $taxCode && '' !== $taxCode ? "Cod. Fiscale {$taxCode}" : null,
        ]));
    }
}
