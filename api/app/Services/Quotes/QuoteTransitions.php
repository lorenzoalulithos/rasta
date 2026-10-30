<?php declare(strict_types=1);

namespace App\Services\Quotes;

use App\Enums\QuoteStatus;
use App\Models\Database\v1\Material;
use App\Models\Database\v1\Quote;
use App\Models\Database\v1\Tenant;
use App\Services\Calculator\Decimal;
use App\Services\Stock\StockService;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Status transitions of a quote, domain.md §6.
 */
final class QuoteTransitions
{
    /**
     * Allowed transitions: from → [to]. Everything else is refused with 409.
     *
     * @var array<string, array<int, QuoteStatus>>
     */
    private const ALLOWED = [
        'draft' => [QuoteStatus::Sent, QuoteStatus::Rejected],
        'sent' => [QuoteStatus::Accepted, QuoteStatus::Rejected, QuoteStatus::Draft],
    ];

    /**
     * @param StockService $stock
     */
    public function __construct(private readonly StockService $stock)
    {
    }

    /**
     * Moves the quote to `$to` and returns the stock warnings of an acceptance.
     *
     * @param Quote       $quote
     * @param QuoteStatus $to
     *
     * @return array<int, array{material_id: string, name: string, package_label: string, available: float, min_stock: float, to_buy: int}>
     */
    public function apply(Quote $quote, QuoteStatus $to): array
    {
        abort_unless(
            in_array($to, self::ALLOWED[$quote->status->value] ?? [], true),
            Response::HTTP_CONFLICT,
            self::refusal($quote->status, $to),
        );

        abort_if(
            QuoteStatus::Sent === $to && !$quote->lines()->exists(),
            Response::HTTP_CONFLICT,
            'Aggiungi almeno un lavoro prima di inviare il preventivo.',
        );

        return DB::transaction(function () use ($quote, $to): array {
            $quote->forceFill([
                'status' => $to,
                'accepted_at' => QuoteStatus::Accepted === $to ? Carbon::now() : $quote->accepted_at,
            ])->save();

            return QuoteStatus::Accepted === $to ? $this->stockWarnings($quote) : [];
        });
    }

    /**
     * Materials of the quote whose available stock, once committed, is below the minimum.
     *
     * @param Quote $quote
     *
     * @return array<int, array{material_id: string, name: string, package_label: string, available: float, min_stock: float, to_buy: int}>
     */
    private function stockWarnings(Quote $quote): array
    {
        $quote->loadMissing('tenant');

        /** @var Tenant $tenant */
        $tenant = $quote->tenant;
        $ratio = (string) $tenant->opened_package_ratio;

        $materials = Material::query()
                             ->whereHas('quoteLineMaterials', static fn ($query) => $query
                                 ->where('selected', true)
                                 ->whereHas('line', static fn ($line) => $line->where('quote_id', $quote->id)))
                             ->with('stock')
                             ->withCommittedPackages()
                             ->orderBy('name')
                             ->get();

        $warnings = [];

        foreach ($materials as $material) {
            $committed = $material->getAttribute('committed_packages');
            $figures = $this->stock->figures($material->stock, $ratio, is_numeric($committed) ? (string) $committed : null);
            $available = Decimal::of($figures['available']);
            $minimum = Decimal::of($material->min_stock);

            if (bccomp($available, $minimum, Decimal::QUANTITY) >= 0) {
                continue;
            }

            $warnings[] = [
                'material_id' => $material->id,
                'name' => $material->name,
                'package_label' => $material->package_label,
                'available' => $figures['available'],
                'min_stock' => (float) $minimum,
                'to_buy' => (int) Decimal::ceil(bcsub($minimum, $available, Decimal::QUANTITY), 0),
            ];
        }

        return $warnings;
    }

    /**
     * @param QuoteStatus $from
     * @param QuoteStatus $to
     *
     * @return string
     */
    private static function refusal(QuoteStatus $from, QuoteStatus $to): string
    {
        return match (true) {
            QuoteStatus::Rejected === $from && QuoteStatus::Draft === $to => 'Un preventivo rifiutato non torna in bozza: duplicalo.',
            QuoteStatus::Draft === $from && QuoteStatus::Accepted === $to => 'Prima segna il preventivo come inviato.',
            QuoteStatus::Accepted === $from && QuoteStatus::Completed !== $to => 'Il preventivo è accettato: si chiude con la chiusura cantiere.',
            default => "Il preventivo è {$from->label()}: non può passare a {$to->label()}.",
        };
    }
}
