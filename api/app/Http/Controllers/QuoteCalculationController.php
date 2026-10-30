<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\PreviewCalculateRequest;
use App\Http\Resources\QuoteLineResource;
use App\Http\Resources\QuoteResource;
use App\Models\Database\v1\Quote;
use App\Models\Database\v1\QuoteLine;
use App\Services\Quotes\BuiltLine;
use App\Services\Quotes\QuoteLines;
use Illuminate\Http\JsonResponse;

/**
 * "Ricalcola" and the live preview of the line bottom sheet (api.md "Preventivi").
 */
final class QuoteCalculationController extends Controller
{
    /**
     * @param QuoteLines $lines
     */
    public function __construct(private readonly QuoteLines $lines)
    {
    }

    /**
     * POST /quotes/{id}/calculate: snapshot refreshed with the current catalog and settings.
     *
     * @param Quote $quote
     *
     * @return QuoteResource
     */
    public function calculate(Quote $quote): QuoteResource
    {
        QuoteLines::assertEditable($quote);

        $this->lines->recalculate($quote);

        return new QuoteResource($quote->refresh()->load(['site.customer', 'lines.materials.material', 'lines.job']));
    }

    /**
     * POST /quotes/{id}/preview-calculate: the lines as they would be saved, and their totals. Nothing is written.
     *
     * @param PreviewCalculateRequest $request
     * @param Quote                   $quote
     *
     * @return JsonResponse
     */
    public function preview(PreviewCalculateRequest $request, Quote $quote): JsonResponse
    {
        /** @var array<int, array<string, mixed>> $payloads */
        $payloads = $request->validated('lines');

        $built = array_map(function (array $payload) use ($quote): BuiltLine {
            $existing = isset($payload['id']) ? QuoteLine::query()->whereKey((string) $payload['id'])->firstOrFail() : null;

            return $this->lines->make($quote, $payload, $existing);
        }, $payloads);

        $totals = $this->lines->totals($quote, array_map(static fn (BuiltLine $line) => $line->result, $built));

        return response()->json([
            'data' => [
                'lines' => array_map(static fn (BuiltLine $line): array => new QuoteLineResource($line->line)->resolve($request), $built),
                'totals' => [
                    'subtotal' => (float) $totals->subtotal,
                    'vat_amount' => (float) $totals->vatAmount,
                    'total' => (float) $totals->total,
                    'estimated_cost' => (float) $totals->estimatedCost,
                    'estimated_margin' => (float) $totals->estimatedMargin,
                    'estimated_margin_pct' => null === $totals->estimatedMarginPct ? null : (float) $totals->estimatedMarginPct,
                    'estimated_hours' => null === $totals->estimatedHours ? null : (float) $totals->estimatedHours,
                ],
            ],
        ]);
    }
}
