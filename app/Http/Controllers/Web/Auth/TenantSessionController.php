<?php

namespace App\Http\Controllers\Web\Auth;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\TenantUser;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TenantSessionController extends Controller
{
    public function create(): View
    {
        return view('web.auth.login', [
            'page' => [
                'title' => 'Login tenant dashboard',
                'description' => 'Guard tenant memakai tabel tenant_users. Login ini sudah berjalan untuk akun demo lokal.',
                'eyebrow' => 'Tenant Access',
            ],
            'demoCredentials' => app()->isLocal() ? [
                'email' => 'owner@demo.test',
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

        if (! Auth::guard('web')->attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withErrors(['email' => 'Email atau password tenant tidak valid.'])
                ->onlyInput('email');
        }

        /** @var TenantUser $user */
        $user = Auth::guard('web')->user();

        if ($user->user_status !== UserStatus::ACTIVE) {
            Auth::guard('web')->logout();

            return back()
                ->withErrors(['email' => 'User tenant ini sedang inactive.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        return to_route('tenant.dashboard')->with(config('platform.flash_session_key'), [
            'tone' => 'success',
            'title' => 'Login berhasil',
            'message' => 'Selamat datang di dashboard Anda.',
        ]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('home');
    }
}
