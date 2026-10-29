<?php declare(strict_types=1);

use Illuminate\Http\Response;
use function Pest\Laravel\get;

it('returns a successful response', function (): void {
    get('/')->assertStatus(Response::HTTP_OK);
});
