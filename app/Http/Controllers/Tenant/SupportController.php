<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTenantSupportRequest;
use App\Models\TenantSupportRequest;
use App\Models\TenantUser;
use App\Support\Navigation\TenantNavigation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SupportController extends Controller
{
    public function index(): View
    {
        /** @var TenantUser $user */
        $user = auth('web')->user();

        return view('tenant.support.index', [
            'page' => [
                'title' => '',
                'html_title' => 'Bantuan & Feedback',
                'description' => '',
                'eyebrow' => '',
            ],
            'toolbar' => [
                'search_label' => '',
                'search_placeholder' => '',
                'secondary_action' => null,
                'primary_action' => null,
            ],
            'navigation' => TenantNavigation::items($user),
            'authUser' => $user,
            'supportChannels' => [
                'whatsapp_url' => $this->resolveWhatsappUrl($user),
                'feedback_form_url' => $this->resolveFeedbackFormUrl(),
            ],
        ]);
    }

    public function store(StoreTenantSupportRequest $request): RedirectResponse
    {
        /** @var TenantUser $user */
        $user = $request->user('web');

        if (! Schema::hasTable('tenant_support_requests')) {
            Log::warning('Tenant support request table is missing during support form submit.', [
                'tenant_id' => $user->tenant_id,
                'tenant_user_id' => $user->id,
            ]);

            return to_route('tenant.support')->with(config('platform.flash_session_key'), [
                'tone' => 'warning',
                'title' => 'Form support belum siap dipakai',
                'message' => 'Penyimpanan permintaan bantuan belum aktif di server ini. Jalankan migration terbaru atau gunakan channel WhatsApp CS terlebih dahulu.',
            ]);
        }

        TenantSupportRequest::query()->create([
            'tenant_id' => $user->tenant_id,
            'tenant_user_id' => $user->id,
            'subject' => $request->validated('subject'),
            'message' => $request->validated('message'),
            'status' => 'new',
        ]);

        return to_route('tenant.support')->with(config('platform.flash_session_key'), [
            'tone' => 'success',
            'title' => 'Permintaan bantuan terkirim',
            'message' => 'Pesan Anda sudah kami simpan. Tim support bisa menindaklanjuti dari dashboard internal atau channel CS yang aktif.',
        ]);
    }

    private function resolveWhatsappUrl(TenantUser $user): ?string
    {
        $number = preg_replace('/\D+/', '', (string) config('platform.support.whatsapp_number'));

        if ($number === '') {
            return null;
        }

        $message = strtr((string) config('platform.support.whatsapp_message_template'), [
            '{{user_name}}' => $user->name,
            '{{tenant_name}}' => $user->tenant->name ?: 'tenant',
        ]);

        return 'https://wa.me/'.$number.'?text='.rawurlencode(trim($message));
    }

    private function resolveFeedbackFormUrl(): ?string
    {
        $url = trim((string) config('platform.support.feedback_form_url'));

        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return $url;
    }
}
