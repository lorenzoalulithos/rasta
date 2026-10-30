<?php declare(strict_types=1);

namespace App\Services\Quotes;

use App\Models\Database\v1\Tenant;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Next quote number per tenant and year (domain.md §5.1).
 *
 * The counter row is read FOR UPDATE: two quotes created at the same time wait for each
 * other instead of getting the same number. Call it inside the transaction that creates
 * the quote, so the lock lasts until the quote is saved. The counter never goes back,
 * so a deleted quote's number is never reused.
 */
final class QuoteNumbering
{
    /**
     * @var string
     */
    private const TABLE = 'quote_counters';

    /**
     * @param Tenant $tenant
     * @param int    $year
     *
     * @throws LogicException outside a transaction: the lock would be released at once
     *
     * @return int
     */
    public function next(Tenant $tenant, int $year): int
    {
        if (0 === DB::transactionLevel()) {
            throw new LogicException('QuoteNumbering::next() must run inside the transaction that creates the quote.');
        }

        $key = ['tenant_id' => $tenant->id, 'year' => $year];

        // First quote of the year: create the row, ignoring a concurrent creator
        DB::table(self::TABLE)->insertOrIgnore([...$key, 'last_number' => 0]);

        $current = DB::table(self::TABLE)->where($key)->lockForUpdate()->value('last_number');
        $next = (int) $current + 1;

        DB::table(self::TABLE)->where($key)->update(['last_number' => $next]);

        return $next;
    }
}
