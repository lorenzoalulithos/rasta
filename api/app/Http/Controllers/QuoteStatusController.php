<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\TransitionRequest;
use App\Http\Resources\QuoteResource;
use App\Models\Database\v1\Quote;
use App\Services\Quotes\QuoteDuplicator;
use App\Services\Quotes\QuotePdf;
use App\Services\Quotes\QuoteTransitions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Status transitions, duplicate and PDF of a quote (domain.md §5.8, §6, api.md "Preventivi").
 */
final class QuoteStatusController extends Controller
{
    /**
     * @param TransitionRequest $request
     * @param Quote             $quote
     * @param QuoteTransitions  $transitions
     *
     * @return JsonResponse
     */
    public function transition(TransitionRequest $request, Quote $quote, QuoteTransitions $transitions): JsonResponse
    {
        $warnings = $transitions->apply($quote, $request->target());

        return response()->json([
            'data' => new QuoteResource(self::withRelations($quote))->resolve($request),
            'stock_warnings' => $warnings,
        ]);
    }

    /**
     * @param Quote            $quote
     * @param QuoteDuplicator  $duplicator
     *
     * @return JsonResponse
     */
    public function duplicate(Quote $quote, QuoteDuplicator $duplicator): JsonResponse
    {
        return new QuoteResource(self::withRelations($duplicator->duplicate($quote)))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * GET /quotes/{id}/pdf: generated on the fly, never cached (flows.md §2.5).
     *
     * @param Quote    $quote
     * @param QuotePdf $pdf
     *
     * @return Response
     */
    public function pdf(Quote $quote, QuotePdf $pdf): Response
    {
        return response($pdf->render($quote), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $pdf->filename($quote) . '"',
        ]);
    }

    /**
     * @param Quote $quote
     *
     * @return Quote
     */
    private static function withRelations(Quote $quote): Quote
    {
        return $quote->refresh()->load(['site.customer', 'lines.materials.material', 'lines.job']);
    }
}
