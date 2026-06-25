<?php

namespace App\Http\Controllers\Web\Auth;

use App\Enums\TenantType;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterTenantOwnerRequest;
use App\Services\TenantOwnerRegistrationService;
use App\Services\TenantVerificationCodeService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use App\Models\TenantUser;

class TenantRegistrationController extends Controller
{
    private const REGISTRATION_SUCCESS_SESSION_KEY = 'registration_success';

    public function __construct(
        private readonly TenantOwnerRegistrationService $tenantOwnerRegistrationService,
        private readonly TenantVerificationCodeService $tenantVerificationCodeService,
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
        $request->session()->put(self::REGISTRATION_SUCCESS_SESSION_KEY, $this->buildRegistrationSuccessPayload(
            ownerId: $result['owner']->id,
            ownerName: $result['owner']->name,
            ownerWhatsapp: $result['owner']->whatsapp_number,
            activationCode: $result['activation_code'],
            expiresAtIso: $result['activation_expires_at'],
            whatsappSent: $result['whatsapp_sent'],
        ));

        return to_route('tenant.register.success');
    }

    public function success(Request $request): View|RedirectResponse
    {
        $payload = $request->session()->get(self::REGISTRATION_SUCCESS_SESSION_KEY);

        if (! is_array($payload)) {
            return to_route('tenant.register.create')->with(config('platform.flash_session_key'), [
                'tone' => 'warning',
                'title' => 'Sesi registrasi tidak ditemukan',
                'message' => 'Silakan isi form registrasi kembali untuk membuat tenant baru.',
            ]);
        }

        return view('web.auth.register-success', [
            'page' => $payload,
        ]);
    }

    public function regenerate(Request $request): RedirectResponse
    {
        $payload = $request->session()->get(self::REGISTRATION_SUCCESS_SESSION_KEY);

        if (! is_array($payload) || ! isset($payload['owner_id'])) {
            return to_route('tenant.register.create')->with(config('platform.flash_session_key'), [
                'tone' => 'warning',
                'title' => 'Sesi registrasi tidak ditemukan',
                'message' => 'Silakan registrasi ulang untuk membuat kode aktivasi baru.',
            ]);
        }

        $owner = TenantUser::query()->find($payload['owner_id']);

        if (! $owner) {
            $request->session()->forget(self::REGISTRATION_SUCCESS_SESSION_KEY);

            return to_route('tenant.register.create')->with(config('platform.flash_session_key'), [
                'tone' => 'warning',
                'title' => 'Akun owner tidak ditemukan',
                'message' => 'Silakan registrasi ulang untuk membuat akun baru.',
            ]);
        }

        $result = $this->tenantVerificationCodeService->regenerateForTenantOwner($owner, $owner);
        $expiresAtIso = now()->addMinutes((int) config('platform.timeouts.activation_code_minutes'))->toIso8601String();

        $request->session()->put(self::REGISTRATION_SUCCESS_SESSION_KEY, $this->buildRegistrationSuccessPayload(
            ownerId: $owner->id,
            ownerName: $owner->name,
            ownerWhatsapp: $owner->whatsapp_number,
            activationCode: $result['code'],
            expiresAtIso: $expiresAtIso,
            whatsappSent: $result['whatsapp_sent'],
        ));

        return to_route('tenant.register.success')->with(config('platform.flash_session_key'), [
            'tone' => 'success',
            'title' => 'Kode aktivasi baru dibuat',
            'message' => $result['whatsapp_sent']
                ? 'Kode aktivasi baru juga sudah dikirim ke WhatsApp owner.'
                : 'Kode aktivasi baru sudah dibuat, tetapi pengiriman otomatis ke WhatsApp belum berhasil.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRegistrationSuccessPayload(
        int $ownerId,
        string $ownerName,
        string $ownerWhatsapp,
        string $activationCode,
        string $expiresAtIso,
        bool $whatsappSent,
    ): array {
        $command = 'AKTIF '.$activationCode;
        $botTarget = $this->resolveBotTarget();

        return [
            'owner_id' => $ownerId,
            'owner_name' => $ownerName,
            'owner_whatsapp' => $ownerWhatsapp,
            'activation_code' => $activationCode,
            'activation_command' => $command,
            'activation_expires_at' => $expiresAtIso,
            'whatsapp_sent' => $whatsappSent,
            'bot_name' => 'MACAU',
            'bot_display_number' => $botTarget['display_number'] ?? null,
            'whatsapp_url' => isset($botTarget['wa_number'])
                ? 'https://wa.me/'.$botTarget['wa_number'].'?text='.rawurlencode($command)
                : null,
            'status_note' => $whatsappSent
                ? 'Kode aktivasi juga sudah dikirim otomatis ke WhatsApp Anda.'
                : 'Pengiriman WhatsApp otomatis belum berhasil, jadi gunakan kode aktivasi di halaman ini.',
        ];
    }

    /**
     * @return array{display_number?: string, wa_number?: string}
     */
    private function resolveBotTarget(): array
    {
        $bot = DB::table('bot_instances')
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first(['bot_whatsapp_number', 'bot_whatsapp_number_normalized']);

        if (! $bot) {
            return [];
        }

        return [
            'display_number' => $bot->bot_whatsapp_number ?: $bot->bot_whatsapp_number_normalized,
            'wa_number' => $bot->bot_whatsapp_number_normalized ?: null,
        ];
    }
}
