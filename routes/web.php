<?php

use App\Http\Controllers\DatasetPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DatasetPageController::class, 'home'])->name('home');
Route::get('/explore', [DatasetPageController::class, 'index'])->name('explore');
Route::get('/search', [DatasetPageController::class, 'search'])->name('search');
Route::get('/datasets', [DatasetPageController::class, 'index'])->name('datasets.index');
Route::get('/datasets/{dataset:code}', [DatasetPageController::class, 'show'])->name('datasets.show');
Route::get('/downloads', [DatasetPageController::class, 'downloads'])->name('downloads');
Route::get('/topics', [DatasetPageController::class, 'topics'])->name('topics.index');
Route::get('/about', [DatasetPageController::class, 'about'])->name('about');
Route::get('/countries/{countryCode}', [DatasetPageController::class, 'country'])->name('countries.show');
Route::get('/geographies/{pcode}', [DatasetPageController::class, 'geography'])->name('geographies.show');
Route::get('/methodologies/{code}', [DatasetPageController::class, 'methodology'])->name('methodologies.show');
Route::get('/stories/{slug}', [DatasetPageController::class, 'story'])->name('stories.show');
Route::get('/{theme}', [DatasetPageController::class, 'theme'])
    ->whereIn('theme', ['emissions', 'rainfall', 'floods', 'risk', 'policy', 'finance', 'agriculture', 'environment'])
    ->name('themes.show');
