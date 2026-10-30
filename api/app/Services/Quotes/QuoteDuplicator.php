<?php declare(strict_types=1);

namespace App\Services\Quotes;

use App\Enums\QuoteStatus;
use App\Models\Database\v1\Quote;
use App\Models\Database\v1\QuoteLine;
use App\Models\Database\v1\QuoteLineMaterial;
use App\Models\Database\v1\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * POST /quotes/{id}/duplicate: a new draft with the same lines, recalculated with today's catalog.
 */
final class QuoteDuplicator
{
    /**
     * @param QuoteNumbering $numbering
     * @param QuoteLines     $lines
     */
    public function __construct(
        private readonly QuoteNumbering $numbering,
        private readonly QuoteLines $lines,
    ) {
    }

    /**
     * @param Quote $original
     *
     * @return Quote
     */
    public function duplicate(Quote $original): Quote
    {
        $original->loadMissing(['tenant', 'lines.materials']);

        /** @var Tenant $tenant */
        $tenant = $original->tenant;
        $issuedAt = Carbon::today();

        return DB::transaction(function () use ($original, $tenant, $issuedAt): Quote {
            $copy = Quote::query()->create([
                'tenant_id' => $tenant->id,
                'site_id' => $original->site_id,
                'year' => $issuedAt->year,
                'number' => $this->numbering->next($tenant, $issuedAt->year),
                'issued_at' => $issuedAt,
                'expires_at' => $issuedAt->copy()->addDays($tenant->quote_validity_days),
                'status' => QuoteStatus::Draft,
                'vat_rate' => $original->vat_rate,
                'payment_terms' => $original->payment_terms,
                'footer_text' => $original->footer_text,
            ]);

            foreach ($original->lines as $line) {
                self::copyLine($line, $copy);
            }

            $this->lines->recalculate($copy);

            return $copy;
        });
    }

    /**
     * @param QuoteLine $line
     * @param Quote     $copy
     *
     * @return void
     */
    private static function copyLine(QuoteLine $line, Quote $copy): void
    {
        $newLine = $copy->lines()->create([
            ...$line->only(['tenant_id', 'job_id', 'description', 'unit', 'quantity', 'coats', 'unit_price', 'amount', 'sort_order']),
        ]);

        $line->materials->each(static function (QuoteLineMaterial $material) use ($newLine): void {
            $newLine->materials()->create($material->only([
                'tenant_id', 'material_id', 'role', 'consumption_mode', 'consumption_per_unit', 'apply_coats', 'selected',
                'coverage_used', 'required_quantity', 'required_packages', 'unit_cost', 'estimated_cost', 'sort_order',
            ]));
        });
    }
}
