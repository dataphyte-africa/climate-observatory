<?php

use App\Http\Controllers\Api\DatasetApiController;
use App\Http\Controllers\Api\DatasetLookupApiController;
use App\Http\Controllers\Api\EmissionsDashboardApiController;
use App\Http\Controllers\Api\MapApiController;
use Illuminate\Support\Facades\Route;

Route::get('lookups', DatasetLookupApiController::class)
    ->middleware('throttle:lookups');
Route::get('emissions/dashboard', EmissionsDashboardApiController::class)
    ->middleware('throttle:lookups');

Route::prefix('datasets')->group(function () {
    Route::get('/', [DatasetApiController::class, 'index']);
    Route::get('{dataset:code}/export.csv', [DatasetApiController::class, 'exportCsv']);
    Route::get('{dataset:code}/export.json', [DatasetApiController::class, 'exportJson']);
    Route::get('{dataset:code}', [DatasetApiController::class, 'show']);
    Route::get('{dataset:code}/records', [DatasetApiController::class, 'records']);
});

Route::prefix('maps')->middleware('throttle:maps')->group(function () {
    Route::get('geographies/{level}/geometry', [MapApiController::class, 'geometry'])
        ->whereNumber('level');
    Route::get('geographies/{code}/children', [MapApiController::class, 'children']);
    Route::get('datasets/{dataset:code}/values', [MapApiController::class, 'values']);
    Route::get('datasets/{dataset:code}/choropleth', [MapApiController::class, 'choropleth']);
    Route::get('datasets/{dataset:code}/legend', [MapApiController::class, 'legend']);
    Route::get('datasets/{dataset:code}/quality', [MapApiController::class, 'quality']);
});
