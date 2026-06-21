<?php

use App\Http\Controllers\Web\Auth\TenantRegistrationController;
use App\Http\Controllers\Web\Auth\TenantSessionController;
use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\Webhook\WahaWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::post('/webhooks/waha', WahaWebhookController::class)->name('webhooks.waha');

Route::middleware('guest:web')->group(function (): void {
    Route::get('/register', [TenantRegistrationController::class, 'create'])->name('tenant.register.create');
    Route::post('/register', [TenantRegistrationController::class, 'store'])->name('tenant.register.store');

    Route::get('/login', [TenantSessionController::class, 'create'])->name('tenant.login.create');
    Route::post('/login', [TenantSessionController::class, 'store'])->name('tenant.login.store');
});

Route::middleware('auth:web')->group(function (): void {
    Route::post('/logout', [TenantSessionController::class, 'destroy'])->name('tenant.logout');
});
