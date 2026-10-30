<?php declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Database\v1\Quote;
use App\Services\Calculator\Decimal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Quote (Preventivo), domain.md §3.8, with customer, site and totals (api.md).
 *
 * Cost and margin are internal: they never go on the PDF.
 *
 * @mixin Quote
 */
final class QuoteResource extends JsonResource
{
    /**
     * Decimals of the margin percentage.
     *
     * @var int
     */
    private const PERCENT_DECIMALS = 1;

    /**
     * @param Request $request
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'year' => $this->year,
            'number_label' => "{$this->number}/{$this->year}",
            'issued_at' => $this->issued_at->toDateString(),
            'expires_at' => $this->expires_at->toDateString(),
            'status' => $this->status,
            'vat_rate' => (float) $this->vat_rate,
            'payment_terms' => $this->payment_terms,
            'footer_text' => $this->footer_text,
            'private_notes' => $this->private_notes,
            'subtotal' => (float) $this->subtotal,
            'vat_amount' => (float) $this->vat_amount,
            'total' => (float) $this->total,
            'estimated_cost' => (float) $this->estimated_cost,
            'estimated_margin' => (float) $this->estimated_margin,
            'estimated_margin_pct' => $this->marginPct(),
            'estimated_hours' => null,
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'site' => $this->whenLoaded('site', fn (): SiteResource => new SiteResource($this->site)),
            'customer' => $this->whenLoaded('site', fn (): ?array => null === $this->site?->customer ? null : [
                'id' => $this->site->customer->id,
                'name' => $this->site->customer->name,
            ]),
            'lines' => QuoteLineResource::collection($this->whenLoaded('lines')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Margin on the subtotal in %, null on an empty quote.
     *
     * @return float|null
     */
    private function marginPct(): ?float
    {
        $subtotal = Decimal::of($this->subtotal);

        if (!Decimal::isPositive($subtotal)) {
            return null;
        }

        $pct = bcdiv(bcmul(Decimal::of($this->estimated_margin), '100', Decimal::WORKING_SCALE), $subtotal, Decimal::WORKING_SCALE);

        return (float) Decimal::round($pct, self::PERCENT_DECIMALS);
    }
}
