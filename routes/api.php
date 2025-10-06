<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group.
|
*/

// GraphQL endpoint - Single API entry point with custom CORS
Route::post('/graphql', \Nuwave\Lighthouse\Http\GraphQLController::class)->middleware(\Bu\Server\Http\Middleware\GraphQLCors::class);

// Optional: GraphQL playground for development (if you want to test queries)
Route::get('/graphql-playground', function () {
    return view('server.graphql-playground');
});

// Google Workspace webhook endpoint
Route::post('/gws/webhook', [Bu\Gws\Http\Controllers\GoogleWorkspaceWebhookController::class, 'handle']);