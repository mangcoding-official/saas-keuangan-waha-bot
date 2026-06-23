<?php

use App\Services\ConversationSessionService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('conversation-sessions:expire', function (ConversationSessionService $service): void {
    $expiredCount = $service->expireAllStaleSessions(now());

    $this->info($expiredCount.' conversation session expired.');
})->purpose('Expire idle conversation sessions and release their active locks');

Schedule::command('conversation-sessions:expire')
    ->everyMinute()
    ->withoutOverlapping();
