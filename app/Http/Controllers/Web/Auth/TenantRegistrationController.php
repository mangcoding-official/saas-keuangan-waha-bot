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
    ) {}

    public function create(): View
    {
        $timezones = collect(config('platform.supported_timezones'))
            ->map(fn(string $timezone): array => [
                'value' => $timezone,
                'label' => $this->formatTimezoneLabel($timezone),
            ])
            ->all();

        return view('web.auth.register', [
            'page' => [
                'title' => 'Buat akun di MACAU Bot',
                'description' => 'Langkah awal menuju pengelolaan keuangan yang lebih transparan dan terukur.',
            ],
            'tenantTypes' => TenantType::cases(),
            'timezones' => $timezones,
        ]);
    }

    private function formatTimezoneLabel(string $timezone): string
    {
        return match ($timezone) {
            'Asia/Jakarta' => '(GMT+07:00) Jakarta (WIB)',
            'Asia/Makassar' => '(GMT+08:00) Makassar (WITA)',
            'Asia/Jayapura' => '(GMT+09:00) Jayapura (WIT)',
            default => $timezone,
        };
    }

    public function store(RegisterTenantOwnerRequest $request): RedirectResponse
    {
        $result = $this->tenantOwnerRegistrationService->register($request->validated());
        $deliveryNote = $result['whatsapp_sent']
            ? ' Activation code juga sudah dikirim ke WhatsApp owner.'
            : ' Namun pesan WhatsApp otomatis ke owner belum berhasil dikirim.';

        return to_route('tenant.login.create')->with(config('platform.flash_session_key'), [
            'tone' => 'success',
            'title' => 'Registrasi owner berhasil',
            'message' => 'Tenant dan owner berhasil dibuat. Login ke dashboard lalu verifikasi nomor via bot dengan command AKTIF ' . $result['activation_code'] . '.' . $deliveryNote,
        ]);
    }
}
