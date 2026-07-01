<?php

use App\Http\Controllers\Tenant\AccountController;
use App\Http\Controllers\Tenant\AttachmentController;
use App\Http\Controllers\Tenant\AuditController;
use App\Http\Controllers\Tenant\CategoryController;
use App\Http\Controllers\Tenant\DashboardController;
use App\Http\Controllers\Tenant\MemberController;
use App\Http\Controllers\Tenant\ProfileController;
use App\Http\Controllers\Tenant\ResourcePageController;
use App\Http\Controllers\Tenant\SupportController;
use App\Http\Controllers\Tenant\TransactionController;
use Illuminate\Support\Facades\Route;

Route::prefix(config('platform.route_prefixes.tenant'))
    ->as('tenant.')
    ->middleware('auth:web')
    ->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
        Route::get('/attachments/{attachmentId}', [AttachmentController::class, 'show'])->name('attachments.show');
        Route::get('/support', [SupportController::class, 'index'])->name('support');
        Route::post('/support', [SupportController::class, 'store'])->name('support.store');

        Route::get('/profile', ProfileController::class)->name('profile.show');

        Route::middleware('tenant.role:owner')->group(function (): void {
            Route::put('/transactions/{transactionId}', [TransactionController::class, 'update'])->name('transactions.update');
            Route::post('/transactions/{transactionId}/void', [TransactionController::class, 'void'])->name('transactions.void');

            Route::get('/accounts', [AccountController::class, 'index'])->name('accounts.index');
            Route::get('/accounts/create', [AccountController::class, 'create'])->name('accounts.create');
            Route::get('/accounts/{accountId}/edit', [AccountController::class, 'edit'])->name('accounts.edit');
            Route::post('/accounts', [AccountController::class, 'store'])->name('accounts.store');
            Route::put('/accounts/{accountId}', [AccountController::class, 'update'])->name('accounts.update');
            Route::post('/accounts/{accountId}/set-default', [AccountController::class, 'setDefault'])->name('accounts.set-default');
            Route::post('/accounts/{accountId}/activate', [AccountController::class, 'activate'])->name('accounts.activate');
            Route::post('/accounts/{accountId}/deactivate', [AccountController::class, 'deactivate'])->name('accounts.deactivate');

            Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
            Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
            Route::put('/categories/{categoryId}', [CategoryController::class, 'update'])->name('categories.update');
            Route::post('/categories/{categoryId}/activate', [CategoryController::class, 'activate'])->name('categories.activate');
            Route::post('/categories/{categoryId}/deactivate', [CategoryController::class, 'deactivate'])->name('categories.deactivate');

            Route::get('/members', [MemberController::class, 'index'])
                ->name('members.index');
            Route::post('/members', [MemberController::class, 'store'])->name('members.store');
            Route::post('/members/{memberId}/resend', [MemberController::class, 'resend'])->name('members.resend');
            Route::post('/members/{memberId}/regenerate', [MemberController::class, 'regenerate'])->name('members.regenerate');

            Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');

            Route::get('/settings', [ResourcePageController::class, 'show'])
                ->defaults('page', 'settings')
                ->name('settings.index');
        });
    });
