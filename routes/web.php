<?php

use Illuminate\Support\Facades\Route;
use Nawasara\Alerting\Livewire\Dashboard\Index as DashboardIndex;
use Nawasara\Alerting\Livewire\States\Index as StatesIndex;
use Spatie\Permission\Middleware\PermissionMiddleware;

Route::middleware(['web', 'auth'])->prefix('nawasara-alerting')->group(function () {
    Route::get('dashboard', DashboardIndex::class)
        ->middleware(PermissionMiddleware::using('alerting.view'))
        ->name('nawasara-alerting.dashboard');

    Route::get('states', StatesIndex::class)
        ->middleware(PermissionMiddleware::using('alerting.view'))
        ->name('nawasara-alerting.states');
});
