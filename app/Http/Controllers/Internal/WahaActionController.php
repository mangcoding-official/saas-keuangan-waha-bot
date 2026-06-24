<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\PlatformAdminUser;
use App\Services\Waha\WahaManagementService;
use Illuminate\Http\RedirectResponse;
use Throwable;

class WahaActionController extends Controller
{
    public function __construct(
        private readonly WahaManagementService $wahaManagementService,
    ) {
    }

    public function reconnect(int $botInstanceId): RedirectResponse
    {
        /** @var PlatformAdminUser $admin */
        $admin = auth('platform_admin')->user();

        try {
            $result = $this->wahaManagementService->reconnect($admin, $botInstanceId);

            return to_route('internal.waha.index', ['show' => $botInstanceId])->with(config('platform.flash_session_key'), [
                'tone' => 'success',
                'title' => 'Reconnect WAHA dikirim',
                'message' => $result['message'],
            ]);
        } catch (Throwable $exception) {
            return to_route('internal.waha.index', ['show' => $botInstanceId])->with(config('platform.flash_session_key'), [
                'tone' => 'warning',
                'title' => 'Reconnect WAHA gagal',
                'message' => $exception->getMessage(),
            ]);
        }
    }

    public function refreshQr(int $botInstanceId): RedirectResponse
    {
        /** @var PlatformAdminUser $admin */
        $admin = auth('platform_admin')->user();

        try {
            $result = $this->wahaManagementService->refreshQr($admin, $botInstanceId);

            return to_route('internal.waha.index', ['show' => $botInstanceId])->with(config('platform.flash_session_key'), [
                'tone' => $result['qr_data_url'] ? 'success' : 'neutral',
                'title' => 'QR WAHA direfresh',
                'message' => $result['message'],
            ]);
        } catch (Throwable $exception) {
            return to_route('internal.waha.index', ['show' => $botInstanceId])->with(config('platform.flash_session_key'), [
                'tone' => 'warning',
                'title' => 'QR refresh WAHA gagal',
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
