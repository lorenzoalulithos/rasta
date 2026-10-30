{{-- Quote PDF, structure of the partner's quote (domain.md §5.8). Never materials, costs, margin, private notes. --}}
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<title>Preventivo n. {{ $number }} del {{ $date }}</title>
<style>
    @page { margin: 18mm 16mm 22mm 16mm; }
    * { box-sizing: border-box; }
    body { font-family: "DejaVu Sans", sans-serif; font-size: 9.5pt; color: #1f2430; margin: 0; }
    .ink { color: #1f2430; }
    .muted { color: #4a5163; }
    h1 { font-size: 20pt; margin: 0 0 2mm 0; }
    .rule { border-bottom: 1.2pt solid #1f2430; margin-bottom: 2mm; }
    .small { font-size: 8pt; line-height: 1.35; }
    table { border-collapse: collapse; width: 100%; }
    .number-box td { border: 0.8pt solid #1f2430; padding: 2mm 4mm; font-weight: bold; text-align: center; }
    .number-box .label { border: 0; font-weight: normal; padding-right: 2mm; text-align: right; }
    .parties td { vertical-align: top; width: 50%; }
    .party-label { font-size: 8pt; margin-bottom: 1mm; }
    .party { border: 0.8pt solid #1f2430; padding: 3mm 4mm; height: 30mm; line-height: 1.45; }
    .lines th { font-size: 8.5pt; text-align: left; padding: 2mm 1.5mm; border-bottom: 0.8pt solid #1f2430; }
    .lines td { padding: 2mm 1.5mm; vertical-align: top; }
    .num { text-align: right; white-space: nowrap; }
    .notes td { padding: 1mm 1.5mm 2mm 1.5mm; font-size: 8.5pt; line-height: 1.4; }
    /* dompdf: absolute is measured from the page edge, fixed from the content box */
    .summary { margin-top: 10mm; page-break-inside: avoid; }
    .summary.at-bottom { position: absolute; bottom: 28mm; left: 0; right: 0; margin-top: 0; }
    .summary td { border: 1pt solid #1f2430; vertical-align: top; padding: 3mm 4mm; }
    .totals td { border: 0; padding: 1mm 0; }
    .totals .grand td { border-top: 1pt solid #1f2430; padding-top: 2mm; font-weight: bold; font-size: 11pt; }
    .footer { position: fixed; bottom: -14mm; left: 0; right: 0; font-size: 7pt; }
    .footer .page:after { content: counter(page); }
</style>
</head>
<body>

<h1>{{ $tenant['name'] }}</h1>
<div class="rule"></div>
<div class="small">
    @foreach ($tenant['address'] as $part){{ $part }}@if (! $loop->last) - @endif @endforeach
    @if ($tenant['fiscal'] !== '')<br>{{ $tenant['fiscal'] }}@endif
    @if ($tenant['contacts'] !== '')<br>{{ $tenant['contacts'] }}@endif
</div>

<table class="number-box" style="margin: 8mm 0 8mm auto; width: auto;">
    <tr>
        <td class="label">Preventivo nr</td>
        <td>{{ $number }}</td>
        <td class="label">del</td>
        <td>{{ $date }}</td>
    </tr>
</table>

<table class="parties">
    <tr>
        <td style="padding-right: 3mm;">
            <div class="party-label">Destinatario</div>
            <div class="party">
                {{ $recipient['name'] }}<br>
                @foreach ($recipient['address'] as $part){{ $part }}<br>@endforeach
                @if ($recipient['fiscal'] !== '')<span class="small">{{ $recipient['fiscal'] }}</span>@endif
            </div>
        </td>
        <td style="padding-left: 3mm;">
            <div class="party-label">Destinazione</div>
            <div class="party">
                {{ $destination['name'] }}<br>
                {{ $destination['site'] }}<br>
                @foreach ($destination['address'] as $part){{ $part }}<br>@endforeach
            </div>
        </td>
    </tr>
</table>

<table class="lines" style="margin-top: 8mm;">
    <thead>
        <tr>
            <th style="width: 55%;">Descrizione</th>
            <th class="num" style="width: 15%;">Quantità</th>
            <th class="num" style="width: 15%;">Prezzo unitario</th>
            <th class="num" style="width: 15%;">Importo</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($lines as $line)
            <tr>
                <td>{{ $line['description'] }}</td>
                <td class="num">{{ $line['quantity'] }}</td>
                <td class="num">{{ $line['unit_price'] }}</td>
                <td class="num">{{ $line['amount'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="notes" style="margin-top: 3mm; width: 55%;">
    @if (filled($footer_text))
        <tr><td>{{ $footer_text }}</td></tr>
    @endif
    <tr><td>Il presente documento scadrà il {{ $expires_at }}.</td></tr>
</table>

{{-- Short quotes: the summary sits at the bottom like the partner's. Long ones: it follows the lines. --}}
<table class="summary {{ count($lines) <= $linesAtBottom ? 'at-bottom' : '' }}">
    <tr>
        <td style="width: 58%;">
            <strong>Pagamento:</strong> {{ $payment_terms }}
        </td>
        <td style="width: 42%;">
            <table class="totals">
                <tr><td>Totale netto</td><td class="num">{{ $subtotal }} €</td></tr>
                <tr><td>+ Imposta IVA ({{ $vat_rate }}%)</td><td class="num">{{ $vat_amount }} €</td></tr>
                <tr class="grand"><td>Totale</td><td class="num">{{ $total }} €</td></tr>
            </table>
        </td>
    </tr>
</table>

<div class="footer">
    <table>
        <tr>
            <td><strong>Pag. <span class="page"></span></strong></td>
            <td class="num muted">Vi preghiamo di controllare i Vs. dati anagrafici e la Partita IVA. Non ci riteniamo responsabili di eventuali errori.</td>
        </tr>
    </table>
</div>

</body>
</html>
