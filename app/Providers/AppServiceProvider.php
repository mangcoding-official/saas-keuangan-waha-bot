<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $supportNumber = (string) config('platform.support.whatsapp_number', '');
        $supportNumber = preg_replace('/\D+/', '', ltrim($supportNumber, '+')) ?? '';
        $supportMessage = rawurlencode('Halo, saya ingin melakukan pendaftaran akun Macau Bot.');

        if ($supportNumber !== '' && str_starts_with($supportNumber, '0')) {
            $supportNumber = '62'.substr($supportNumber, 1);
        }

        // ponytail: one shared URL beats rebuilding the same wa.me link in every public CTA.
        View::share('supportWhatsappUrl', $supportNumber !== '' ? 'https://wa.me/'.$supportNumber.'?text='.$supportMessage : url('/register'));
    }
}
