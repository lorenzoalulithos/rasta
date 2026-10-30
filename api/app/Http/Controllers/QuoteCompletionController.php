<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CompleteQuoteRequest;
use App\Http\Resources\QuoteResource;
use App\Models\Database\v1\Quote;
use App\Services\Quotes\QuoteCompletion;
use Illuminate\Http\JsonResponse;

/**
 * Chiusura cantiere, flows.md §6 and api.md "Preventivi".
 */
final class QuoteCompletionController extends Controller
{
    /**
     * @param QuoteCompletion $completion
     */
    public function __construct(private readonly QuoteCompletion $completion)
    {
    }

    /**
     * GET /quotes/{id}/completion: the estimated consumption as a proposal.
     *
     * @param Quote $quote
     *
     * @return JsonResponse
     */
    public function show(Quote $quote): JsonResponse
    {
        return response()->json(['data' => $this->completion->proposal($quote)]);
    }

    /**
     * POST /quotes/{id}/complete: `consumption` movements and the quote `completed`.
     *
     * @param CompleteQuoteRequest $request
     * @param Quote                $quote
     *
     * @return JsonResponse
     */
    public function complete(CompleteQuoteRequest $request, Quote $quote): JsonResponse
    {
        $adjustments = $this->completion->complete($quote, $request->consumptions());

        return response()->json([
            'data' => new QuoteResource($quote->refresh()->load(['site.customer', 'lines.materials.material', 'lines.job']))->resolve($request),
            'adjustments' => $adjustments,
        ]);
    }
}
