<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\MaterialKind;
use App\Enums\QuoteStatus;
use App\Http\Resources\QuoteResource;
use App\Models\Database\v1\Material;
use App\Models\Database\v1\Quote;
use App\Models\Database\v1\User;
use App\Services\Stock\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Home, flows.md §1: the three blocks in one call.
 */
final class DashboardController extends Controller
{
    /**
     * How many open quotes the home shows.
     *
     * @var int
     */
    private const OPEN_QUOTES = 5;

    /**
     * Days after the acceptance when a site counts as "da chiudere" (heuristic, flows.md §1).
     *
     * @var int
     */
    private const DAYS_TO_COMPLETE = 14;

    /**
     * GET /dashboard: `{ to_buy_count, open_quotes, to_complete }`.
     *
     * @param Request      $request
     * @param StockService $stock
     *
     * @return JsonResponse
     */
    public function __invoke(Request $request, StockService $stock): JsonResponse
    {
        $user = $request->user();
        $ratio = $user instanceof User ? (string) $user->tenant?->opened_package_ratio : '0.50';

        $toBuy = Material::query()
                         ->whereNull('materials.archived_at')
                         ->where('materials.kind', '!=', MaterialKind::Tool->value);
        $stock->whereToBuy($toBuy, $ratio);

        $openQuotes = Quote::query()
                           ->with('site.customer')
                           ->whereIn('status', [QuoteStatus::Draft, QuoteStatus::Sent, QuoteStatus::Accepted])
                           ->orderByDesc('year')
                           ->orderByDesc('number')
                           ->limit(self::OPEN_QUOTES)
                           ->get();

        $toComplete = Quote::query()
                           ->with('site.customer')
                           ->where('status', QuoteStatus::Accepted)
                           ->where('accepted_at', '<=', Carbon::now()->subDays(self::DAYS_TO_COMPLETE))
                           ->oldest('accepted_at')
                           ->get();

        return response()->json([
            'data' => [
                'to_buy_count' => $toBuy->count(),
                'open_quotes' => QuoteResource::collection($openQuotes)->resolve($request),
                'to_complete' => QuoteResource::collection($toComplete)->resolve($request),
            ],
        ]);
    }
}
