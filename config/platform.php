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

    'password_resets' => [
        'expire_minutes' => 20,
        'max_attempts' => 3,
        'decay_minutes' => 15,
    ],

    'support' => [
        'whatsapp_number' => env('SUPPORT_WHATSAPP_NUMBER'),
        'whatsapp_message_template' => env(
            'SUPPORT_WHATSAPP_MESSAGE',
            'Halo tim support, saya {{user_name}} dari tenant {{tenant_name}} butuh bantuan terkait penggunaan aplikasi.'
        ),
        'feedback_form_url' => env('SUPPORT_FEEDBACK_FORM_URL'),
    ],

    'flash_session_key' => 'status',
];
