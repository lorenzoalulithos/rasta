<?php declare(strict_types=1);

use App\Models\Database\v1\Tenant;
use App\Services\Quotes\QuoteNumbering;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;

// Real commits are needed: a second connection must see the counter row
uses(DatabaseTruncation::class);

// Year of the concurrency test
const LOCK_YEAR = 2026;

it('makes a concurrent creation wait for the counter instead of taking the same number', function (): void {
    $tenant = Tenant::factory()->create();
    $key = ['tenant_id' => $tenant->id, 'year' => LOCK_YEAR];

    config(['database.connections.concurrent' => config('database.connections.' . config('database.default'))]);
    $concurrent = DB::connection('concurrent');
    $concurrent->statement('SET SESSION innodb_lock_wait_timeout = 1');

    // First request: takes number 1 and keeps the row locked until its quote is saved
    DB::beginTransaction();
    $first = new QuoteNumbering()->next($tenant, LOCK_YEAR);

    // Second request meanwhile: cannot read the counter for update, it has to wait
    $blocked = static fn () => $concurrent->transaction(
        static fn () => $concurrent->table('quote_counters')->where($key)->lockForUpdate()->value('last_number')
    );

    expect($first)->toBe(1)
        ->and($blocked)->toThrow(QueryException::class);

    DB::commit();

    // Once the first is saved, the second reads 1 and goes on with 2
    expect($blocked())->toBe(1);

    $concurrent->disconnect();
});
