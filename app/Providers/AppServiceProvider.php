<?php

namespace App\Providers;

use App\Services\Statamic\SafeUpdatesOverview;
use App\Http\Controllers\Cp\EmissionsCpController;
use App\Http\Controllers\Cp\RainfallCpController;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Statamic\Facades\CP\Nav;
use Statamic\Statamic;
use Statamic\Updater\UpdatesOverview;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Keep transient Marketplace/Outpost failures out of normal CP navigation.
        $this->app->singleton(UpdatesOverview::class, SafeUpdatesOverview::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('lookups', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
        RateLimiter::for('maps', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
        Statamic::pushCpRoutes(function (): void {
            Route::get('emissions', [EmissionsCpController::class, 'index'])->name('climatehub.emissions.index');
            Route::post('emissions/inject', [EmissionsCpController::class, 'inject'])->name('climatehub.emissions.inject');
            Route::post('emissions/imports/{import}/approve', [EmissionsCpController::class, 'approveImport'])->name('climatehub.emissions.imports.approve');
            Route::get('emissions/{emissionsValue}/edit', [EmissionsCpController::class, 'edit'])->name('climatehub.emissions.edit');
            Route::patch('emissions/{emissionsValue}', [EmissionsCpController::class, 'update'])->name('climatehub.emissions.update');
            Route::get('rainfall', [RainfallCpController::class, 'index'])->name('climatehub.rainfall.index');
            Route::post('rainfall/inject', [RainfallCpController::class, 'inject'])->name('climatehub.rainfall.inject');
            Route::post('rainfall/imports/{import}/approve', [RainfallCpController::class, 'approveImport'])->name('climatehub.rainfall.imports.approve');
            Route::get('rainfall/{rainfallValue}/edit', [RainfallCpController::class, 'edit'])->name('climatehub.rainfall.edit');
            Route::patch('rainfall/{rainfallValue}', [RainfallCpController::class, 'update'])->name('climatehub.rainfall.update');
        });

        Nav::content('Rainfall Data')
            ->url(url(config('statamic.cp.route', 'cp').'/rainfall'))
            ->icon('upload');

        Nav::content('Emissions Data')
            ->url(url(config('statamic.cp.route', 'cp').'/emissions'))
            ->icon('chart-bar');
    }
}
