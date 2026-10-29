<?php declare(strict_types=1);

use Illuminate\Http\Response;
use function Pest\Laravel\getJson;
use function Pest\Laravel\options;

it('answers the ping with the app status', function (): void {
    getJson('/api/ping')
        ->assertStatus(Response::HTTP_OK)
        ->assertJsonPath('data.status', 'ok')
        ->assertJsonPath('data.app', config('app.name'))
        ->assertJsonStructure(['data' => ['status', 'app', 'time']]);
});

it('allows the Nuxt dev origin', function (): void {
    options('/api/ping', [], [
        'Origin' => 'http://localhost:3000',
        'Access-Control-Request-Method' => 'GET',
    ])->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000');
});

it('rejects unknown origins', function (): void {
    options('/api/ping', [], [
        'Origin' => 'https://evil.example',
        'Access-Control-Request-Method' => 'GET',
    ])->assertHeaderMissing('Access-Control-Allow-Origin');
});
