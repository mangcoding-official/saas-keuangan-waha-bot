<?php

namespace App\Http\Controllers\Web\Auth;

use App\Enums\TenantType;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterTenantOwnerRequest;
use App\Services\TenantOwnerRegistrationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class TenantRegistrationController extends Controller
{
    public function __construct(
        private readonly TenantOwnerRegistrationService $tenantOwnerRegistrationService,
    ) {
    }

    public function create(): View
    {
        return view('web.auth.register', [
            'page' => [
                'title' => 'Registrasi tenant owner',
                'description' => 'Calon owner mendaftarkan tenant, nomor WhatsApp, dan kredensial dashboard. Sistem akan membuat tenant, owner, activation code, akun Cash default, dan kategori template.',
                'eyebrow' => 'Website Registration',
            ],
            'tenantTypes' => TenantType::cases(),
            'timezones' => config('platform.supported_timezones'),
        ]);
    }

    public function store(RegisterTenantOwnerRequest $request): RedirectResponse
    {
        $result = $this->tenantOwnerRegistrationService->register($request->validated());

        return to_route('tenant.login.create')->with(config('platform.flash_session_key'), [
            'tone' => 'success',
            'title' => 'Registrasi owner berhasil',
            'message' => 'Tenant dan owner berhasil dibuat. Login ke dashboard lalu verifikasi nomor via bot dengan command AKTIF '.$result['activation_code'].'.',
        ]);
    }
}
