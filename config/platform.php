<?php

use App\Enums\ServicePlan;

return [
    'route_prefixes' => [
        'tenant' => 'app',
        'internal' => 'internal',
    ],

    'defaults' => [
        'tenant_timezone' => 'Asia/Jakarta',
        'service_plan' => ServicePlan::ALPHA->value,
    ],

    'supported_timezones' => [
        'Asia/Jakarta',
        'Asia/Makassar',
        'Asia/Jayapura',
    ],

    'limits' => [
        'tenant_users' => 5,
    ],

    'timeouts' => [
        'activation_code_minutes' => 15,
        'conversation_session_minutes' => 30,
    ],

    'flash_session_key' => 'status',
];
