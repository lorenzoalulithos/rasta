<?php declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/ping', static fn () => response()->json([
    'data' => [
        'status' => 'ok',
        'app' => config('app.name'),
        'time' => now()->toIso8601String(),
    ],
]))->name('ping');

Route::get('/user', static fn (Request $request) => $request->user())->middleware('auth:sanctum');
