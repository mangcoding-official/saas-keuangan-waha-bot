<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Services\TenantPasswordResetService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TenantPasswordResetController extends Controller
{
    private const NEUTRAL_RESPONSE_MESSAGE = 'Jika nomor terdaftar, link reset password akan dikirim ke WhatsApp Anda.';

    public function __construct(
        private readonly TenantPasswordResetService $tenantPasswordResetService,
    ) {
    }

    public function create(): View
    {
        return view('web.auth.forgot-password', [
            'page' => [
                'title' => 'Reset password via WhatsApp',
                'description' => 'Masukkan nomor WhatsApp yang terdaftar untuk menerima link reset password.',
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'whatsapp_number' => ['required', 'string', 'max:32'],
        ], [
            'whatsapp_number.required' => 'Nomor WhatsApp wajib diisi.',
        ]);

        try {
            $this->tenantPasswordResetService->requestResetLink(
                rawWhatsappNumber: (string) $validated['whatsapp_number'],
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            );
        } catch (ValidationException $exception) {
            throw $exception;
        }

        return back()->with(config('platform.flash_session_key'), [
            'tone' => 'success',
            'title' => 'Permintaan diterima',
            'message' => self::NEUTRAL_RESPONSE_MESSAGE,
        ]);
    }

    public function edit(Request $request, string $lookup): View
    {
        $state = $this->tenantPasswordResetService->resolveResetLink(
            lookupKey: $lookup,
            plainToken: $request->query('token'),
        );

        return view('web.auth.reset-password', [
            'page' => [
                'title' => 'Buat password baru',
                'description' => 'Atur ulang password akun tenant Anda melalui link sekali pakai.',
            ],
            'lookup' => $lookup,
            'plainToken' => (string) $request->query('token', ''),
            'isLinkValid' => $state['is_valid'],
            'linkMessage' => $state['message'],
            'expiresAtIso' => $state['token']?->expires_at?->toIso8601String(),
        ]);
    }

    public function update(Request $request, string $lookup): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
        ]);

        $this->tenantPasswordResetService->resetPassword(
            lookupKey: $lookup,
            plainToken: (string) $validated['token'],
            newPassword: (string) $validated['password'],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return to_route('tenant.login.create')->with(config('platform.flash_session_key'), [
            'tone' => 'success',
            'title' => 'Password berhasil diperbarui',
            'message' => 'Silakan login kembali menggunakan password baru Anda.',
        ]);
    }
}
