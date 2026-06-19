<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TenantRegistrationController extends Controller
{
    public function create(): View
    {
        return view('web.auth.register', [
            'page' => [
                'title' => 'Registrasi tenant owner',
                'description' => 'Struktur form dan response contract sudah dikunci di milestone 0. Logic create tenant akan diisi pada milestone 1.',
                'eyebrow' => 'Website Registration',
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->flash();

        return back()->with(config('platform.flash_session_key'), [
            'tone' => 'warning',
            'title' => 'Contract route aktif',
            'message' => 'Logic registrasi tenant dan owner akan diimplementasikan pada milestone 1 di endpoint ini.',
        ]);
    }
}
