<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\QuoteStatus;
use App\Http\Requests\StoreQuoteRequest;
use App\Http\Requests\UpdateQuoteRequest;
use App\Http\Resources\QuoteResource;
use App\Models\Database\v1\Quote;
use App\Models\Database\v1\Site;
use App\Models\Database\v1\Tenant;
use App\Models\Database\v1\User;
use App\Services\Calculator\CalculatorResolver;
use App\Services\Calculator\Decimal;
use App\Services\Quotes\QuoteNumbering;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Quotes (Preventivi), api.md "Preventivi": header CRUD. Lines, calculation and transitions come next.
 */
final class QuoteController extends Controller
{
    /**
     * GET /quotes?status=&search=, newest first.
     *
     * @param Request $request
     *
     * @return AnonymousResourceCollection
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $search = $request->string('search')->trim()->toString();
        $status = QuoteStatus::tryFrom($request->string('status')->toString());

        $quotes = Quote::query()
                       ->with('site.customer')
                       ->when(null !== $status, static fn (Builder $query) => $query->where('status', $status))
                       ->when('' !== $search, static function (Builder $query) use ($search): void {
                           $query->where(static function (Builder $query) use ($search): void {
                               $query->whereHas('site', static function (Builder $site) use ($search): void {
                                   $site->whereLike('name', "%{$search}%")
                                        ->orWhereHas('customer', static fn (Builder $customer) => $customer->whereLike('name', "%{$search}%"));
                               });

                               if (ctype_digit($search)) {
                                   $query->orWhere('number', (int) $search);
                               }
                           });
                       })
                       ->orderByDesc('year')
                       ->orderByDesc('number')
                       ->paginate($this->perPage($request));

        return QuoteResource::collection($quotes);
    }

    /**
     * POST /quotes: a draft with the next number, VAT, footer and payment terms of the tenant.
     *
     * @param StoreQuoteRequest $request
     * @param QuoteNumbering    $numbering
     *
     * @return JsonResponse
     */
    public function store(StoreQuoteRequest $request, QuoteNumbering $numbering): JsonResponse
    {
        $tenant = self::tenant($request);
        $site = Site::query()->findOrFail($request->string('site_id')->toString());
        $issuedAt = Carbon::today();

        $quote = DB::transaction(static fn (): Quote => Quote::query()->create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'year' => $issuedAt->year,
            'number' => $numbering->next($tenant, $issuedAt->year),
            'issued_at' => $issuedAt,
            'expires_at' => $issuedAt->copy()->addDays($tenant->quote_validity_days),
            'status' => QuoteStatus::Draft,
            'vat_rate' => $tenant->default_vat_rate,
            'payment_terms' => $tenant->default_payment_terms,
            'footer_text' => $tenant->quote_footer_text,
        ]));

        return new QuoteResource(self::withRelations($quote))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * @param Quote $quote
     *
     * @return QuoteResource
     */
    public function show(Quote $quote): QuoteResource
    {
        return new QuoteResource(self::withRelations($quote));
    }

    /**
     * Header fields only in draft/sent, private notes always. A sent quote goes back to draft (domain.md §6).
     *
     * @param UpdateQuoteRequest $request
     * @param Quote              $quote
     * @param CalculatorResolver $calculators
     *
     * @return QuoteResource
     */
    public function update(UpdateQuoteRequest $request, Quote $quote, CalculatorResolver $calculators): QuoteResource
    {
        $changesDocument = $request->changesDocument();

        abort_if(
            $changesDocument && !$quote->status->isEditable(),
            Response::HTTP_CONFLICT,
            "Il preventivo è {$quote->status->label()}: si possono cambiare solo le note private."
        );

        $quote->fill($request->validated());

        if ($request->has('issued_at')) {
            $quote->expires_at = Carbon::parse($request->string('issued_at')->toString())
                                       ->addDays(self::tenant($request)->quote_validity_days);
        }

        if ($request->has('vat_rate')) {
            $calculator = $calculators->for(self::tenant($request));
            $subtotal = Decimal::of($quote->subtotal);
            $vat = $calculator->vatAmount($subtotal, Decimal::of($quote->vat_rate));
            $quote->vat_amount = $vat;
            $quote->total = bcadd($subtotal, $vat, Decimal::MONEY);
        }

        if ($changesDocument && QuoteStatus::Sent === $quote->status) {
            $quote->status = QuoteStatus::Draft;
        }

        $quote->save();

        return new QuoteResource(self::withRelations($quote));
    }

    /**
     * Only drafts. The number is not given back (domain.md §5.1).
     *
     * @param Quote $quote
     *
     * @return Response
     */
    public function destroy(Quote $quote): Response
    {
        abort_if(
            QuoteStatus::Draft !== $quote->status,
            Response::HTTP_CONFLICT,
            "Il preventivo è {$quote->status->label()}: si eliminano solo le bozze."
        );

        $quote->delete();

        return response()->noContent();
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

    /**
     * @param Request $request
     *
     * @return Tenant
     */
    private static function tenant(Request $request): Tenant
    {
        /** @var User $user */
        $user = $request->user();

        /** @var Tenant */
        return $user->tenant;
    }
}
