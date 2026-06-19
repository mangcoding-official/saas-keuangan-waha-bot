<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum IncomingMessageIgnoredReason: string
{
    use HasValues;

    case FROM_ME = 'from_me';
    case NON_PERSONAL_CHAT = 'non_personal_chat';
    case UNKNOWN_SENDER = 'unknown_sender';
    case PENDING_VERIFICATION = 'pending_verification';
    case USER_INACTIVE = 'user_inactive';
    case TENANT_INACTIVE = 'tenant_inactive';
    case SERVICE_INACTIVE = 'service_inactive';
    case DUPLICATE_MESSAGE = 'duplicate_message';
    case INVALID_ACTIVATION_ONLY = 'invalid_activation_only';
    case OTHER = 'other';
}
