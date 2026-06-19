<?php

namespace App\Http\Controllers\Internal\Auth;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\PlatformAdminUser;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PlatformAdminSessionController extends Controller
{
    public function create(): View
    {
        return view('internal.auth.login', [
            'page' => [
                'title' => 'Login platform admin',
                'description' => 'Guard internal memakai provider platform_admin_users dan URL terpisah di /internal.',
                'eyebrow' => 'Platform Admin',
            ],
            'demoCredentials' => app()->isLocal() ? [
                'email' => 'admin@demo.test',
                'password' => 'password',
            ] : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('platform_admin')->attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withErrors(['email' => 'Email atau password platform admin tidak valid.'])
                ->onlyInput('email');
        }

        /** @var PlatformAdminUser $user */
        $user = Auth::guard('platform_admin')->user();

        if ($user->user_status !== UserStatus::ACTIVE) {
            Auth::guard('platform_admin')->logout();

            return back()
                ->withErrors(['email' => 'Akun platform admin sedang inactive.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        return to_route('internal.dashboard')->with(config('platform.flash_session_key'), [
            'tone' => 'success',
            'title' => 'Internal login berhasil',
            'message' => 'Platform admin shell siap dipakai untuk modul support.',
        ]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('platform_admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('internal.login.create');
    }
}
