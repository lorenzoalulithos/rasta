<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\QuoteLineRequest;
use App\Http\Requests\ReorderLinesRequest;
use App\Http\Resources\QuoteLineResource;
use App\Http\Resources\QuoteResource;
use App\Models\Database\v1\Quote;
use App\Models\Database\v1\QuoteLine;
use App\Services\Quotes\QuoteLines;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Lines of a quote, calculated and saved with their material snapshot (api.md "Preventivi").
 */
final class QuoteLineController extends Controller
{
    /**
     * @param QuoteLines $lines
     */
    public function __construct(private readonly QuoteLines $lines)
    {
    }

    /**
     * @param QuoteLineRequest $request
     * @param Quote            $quote
     *
     * @return JsonResponse
     */
    public function store(QuoteLineRequest $request, Quote $quote): JsonResponse
    {
        QuoteLines::assertEditable($quote);

        $line = DB::transaction(function () use ($request, $quote): QuoteLine {
            $line = $this->lines->save($this->lines->make($quote, $request->validated()));
            $this->afterChange($quote);

            return $line;
        });

        return $this->respond($quote, $line, Response::HTTP_CREATED);
    }

    /**
     * @param QuoteLineRequest $request
     * @param Quote            $quote
     * @param QuoteLine        $line
     *
     * @return JsonResponse
     */
    public function update(QuoteLineRequest $request, Quote $quote, QuoteLine $line): JsonResponse
    {
        QuoteLines::assertEditable($quote);

        $line = DB::transaction(function () use ($request, $quote, $line): QuoteLine {
            $saved = $this->lines->save($this->lines->make($quote, $request->validated(), $line), $line);
            $this->afterChange($quote);

            return $saved;
        });

        return $this->respond($quote, $line, Response::HTTP_OK);
    }

    /**
     * @param Quote     $quote
     * @param QuoteLine $line
     *
     * @return Response
     */
    public function destroy(Quote $quote, QuoteLine $line): Response
    {
        QuoteLines::assertEditable($quote);

        DB::transaction(function () use ($quote, $line): void {
            $line->delete();
            $this->afterChange($quote);
        });

        return response()->noContent();
    }

    /**
     * @param ReorderLinesRequest $request
     * @param Quote               $quote
     *
     * @return Response
     */
    public function reorder(ReorderLinesRequest $request, Quote $quote): Response
    {
        QuoteLines::assertEditable($quote);

        /** @var array<int, string> $ids */
        $ids = $request->validated('ids');

        DB::transaction(static function () use ($ids, $quote): void {
            foreach (array_values($ids) as $position => $id) {
                $quote->lines()->whereKey($id)->update(['sort_order' => $position]);
            }

            QuoteLines::backToDraft($quote);
        });

        return response()->noContent();
    }

    /**
     * Totals follow the lines; a sent quote goes back to draft.
     *
     * @param Quote $quote
     *
     * @return void
     */
    private function afterChange(Quote $quote): void
    {
        $this->lines->refreshTotals($quote);
        QuoteLines::backToDraft($quote);
    }

    /**
     * `{ data: line, quote: totals }`: the footer of the quote updates without another call.
     *
     * @param Quote     $quote
     * @param QuoteLine $line
     * @param int       $status
     *
     * @return JsonResponse
     */
    private function respond(Quote $quote, QuoteLine $line, int $status): JsonResponse
    {
        $request = request();

        return response()->json([
            'data' => new QuoteLineResource($line)->resolve($request),
            'quote' => new QuoteResource($quote->refresh()->load('site.customer'))->resolve($request),
        ], $status);
    }
}
