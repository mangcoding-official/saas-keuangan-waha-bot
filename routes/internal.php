<?php

use App\Http\Controllers\Internal\Auth\PlatformAdminSessionController;
use App\Http\Controllers\Internal\AttachmentPreviewController;
use App\Http\Controllers\Internal\DashboardController;
use App\Http\Controllers\Internal\OwnerRegistrationInviteController;
use App\Http\Controllers\Internal\ResourcePageController;
use App\Http\Controllers\Internal\SupportInboxController;
use App\Http\Controllers\Internal\VerificationController;
use App\Http\Controllers\Internal\WahaActionController;
use Illuminate\Support\Facades\Route;

Route::prefix(config('platform.route_prefixes.internal'))
    ->as('internal.')
    ->group(function (): void {
        Route::middleware('guest:platform_admin')->group(function (): void {
            Route::get('/login', [PlatformAdminSessionController::class, 'create'])->name('login.create');
            Route::post('/login', [PlatformAdminSessionController::class, 'store'])->name('login.store');
        });

        Route::middleware('auth:platform_admin')->group(function (): void {
            Route::post('/logout', [PlatformAdminSessionController::class, 'destroy'])->name('logout');
            Route::get('/', DashboardController::class)->name('dashboard');
            Route::get('/attachments/{attachmentId}/preview', [AttachmentPreviewController::class, 'show'])->name('attachments.preview');
            Route::get('/invites', [OwnerRegistrationInviteController::class, 'index'])->name('invites.index');
            Route::post('/invites', [OwnerRegistrationInviteController::class, 'store'])->name('invites.store');
            Route::post('/invites/{inviteId}/send-whatsapp', [OwnerRegistrationInviteController::class, 'sendWhatsapp'])->name('invites.send-whatsapp');
            Route::post('/invites/{inviteId}/revoke', [OwnerRegistrationInviteController::class, 'revoke'])->name('invites.revoke');
            Route::get('/verification', [VerificationController::class, 'index'])->name('verification.index');
            Route::get('/support', [SupportInboxController::class, 'index'])->name('support.index');
            Route::post('/verification/{tenantUserId}/resend', [VerificationController::class, 'resend'])->name('verification.resend');
            Route::post('/verification/{tenantUserId}/regenerate', [VerificationController::class, 'regenerate'])->name('verification.regenerate');
            Route::get('/tenants/{tenantId}', [ResourcePageController::class, 'showTenant'])->name('tenants.show');
            Route::post('/waha/{botInstanceId}/reconnect', [WahaActionController::class, 'reconnect'])->name('waha.reconnect');
            Route::post('/waha/{botInstanceId}/refresh-qr', [WahaActionController::class, 'refreshQr'])->name('waha.refresh-qr');

            foreach ([
                'tenants' => 'tenants.index',
                'users' => 'users.index',
                'sessions' => 'sessions.index',
                'transactions' => 'transactions.index',
                'attachments' => 'attachments.index',
                'waha' => 'waha.index',
                'audit/tenant-facing' => 'audit.tenant-facing.index',
                'audit/platform' => 'audit.platform.index',
            ] as $uri => $name) {
                Route::get('/'.$uri, [ResourcePageController::class, 'show'])
                    ->defaults('page', $name)
                    ->name($name);
            }
        });
    });
