<?php

use App\Http\Controllers\Tenant\DashboardController;
use App\Http\Controllers\Tenant\ResourcePageController;
use Illuminate\Support\Facades\Route;

Route::prefix(config('platform.route_prefixes.tenant'))
    ->as('tenant.')
    ->middleware('auth:web')
    ->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::get('/transactions', [ResourcePageController::class, 'show'])
            ->defaults('page', 'transactions')
            ->name('transactions.index');

        Route::get('/profile', [ResourcePageController::class, 'show'])
            ->defaults('page', 'profile')
            ->name('profile.show');

        Route::middleware('tenant.role:owner')->group(function (): void {
            Route::get('/accounts', [ResourcePageController::class, 'show'])
                ->defaults('page', 'accounts')
                ->name('accounts.index');

            Route::get('/categories', [ResourcePageController::class, 'show'])
                ->defaults('page', 'categories')
                ->name('categories.index');

            Route::get('/members', [ResourcePageController::class, 'show'])
                ->defaults('page', 'members')
                ->name('members.index');

            Route::get('/audit', [ResourcePageController::class, 'show'])
                ->defaults('page', 'audit')
                ->name('audit.index');

            Route::get('/settings', [ResourcePageController::class, 'show'])
                ->defaults('page', 'settings')
                ->name('settings.index');
        });
    });
