<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('web.home', [
            'page' => [
                'title' => 'Foundation contract untuk SaaS Keuangan WAHA',
                'description' => 'Milestone 0 mengunci stack Laravel, guard auth, route map, dan UI shell tanpa AlpineJS.',
                'eyebrow' => 'Milestone 0',
            ],
        ]);
    }
}
