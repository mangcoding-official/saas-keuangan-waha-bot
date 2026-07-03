<?php

namespace App\Services\Waha;

use App\Enums\IncomingMessageAccessDecision;
use App\Enums\IncomingMessageIgnoredReason;
use App\Enums\MessageChatType;
use App\Enums\ServiceStatus;
use App\Enums\TenantStatus;
use App\Enums\UserStatus;
use App\Enums\VerificationStatus;
use Illuminate\Support\Facades\DB;

class WahaAccessGateService
{
    /**
     * @param  array{
     *     event_name: string,
     *     from_me: bool,
     *     chat_type: string,
     *     sender_normalized: ?string,
     *     message_text: ?string
     * }  $message
     * @return array{
     *     access_decision: string,
     *     ignored_reason: ?string,
     *     route: string,
     *     tenant_user: ?object,
     *     tenant_id: ?int,
     *     should_reply: bool
     * }
     */
    public function evaluate(array $message): array
    {
        if ($message['event_name'] !== 'message') {
            return $this->ignored(IncomingMessageIgnoredReason::OTHER);
        }

        if ($message['from_me']) {
            return $this->ignored(IncomingMessageIgnoredReason::FROM_ME);
        }

        if ($message['chat_type'] !== MessageChatType::PERSONAL->value) {
            return $this->ignored(IncomingMessageIgnoredReason::NON_PERSONAL_CHAT);
        }

        if ($message['sender_normalized'] === null) {
            return $this->ignored(IncomingMessageIgnoredReason::UNKNOWN_SENDER);
        }

        $tenantUser = DB::table('tenant_users')
            ->join('tenants', 'tenants.id', '=', 'tenant_users.tenant_id')
            ->where('tenant_users.whatsapp_number_normalized', $message['sender_normalized'])
            ->first([
                'tenant_users.id',
                'tenant_users.tenant_id',
                'tenant_users.user_status',
                'tenant_users.verification_status',
                'tenants.tenant_status',
                'tenants.service_status',
            ]);

        if (! $tenantUser) {
            return $this->ignored(IncomingMessageIgnoredReason::UNKNOWN_SENDER);
        }

        if ($tenantUser->tenant_status !== TenantStatus::ACTIVE->value) {
            return $this->ignored(IncomingMessageIgnoredReason::TENANT_INACTIVE, $tenantUser);
        }

        if ($tenantUser->service_status !== ServiceStatus::ACTIVE->value) {
            return $this->ignored(IncomingMessageIgnoredReason::SERVICE_INACTIVE, $tenantUser);
        }

        if ($tenantUser->user_status !== UserStatus::ACTIVE->value) {
            return $this->ignored(IncomingMessageIgnoredReason::USER_INACTIVE, $tenantUser);
        }

        if ($tenantUser->verification_status === VerificationStatus::PENDING_VERIFICATION->value) {
            if (! $this->looksLikeActivationCommand($message['message_text'])) {
                return $this->ignored(IncomingMessageIgnoredReason::PENDING_VERIFICATION, $tenantUser);
            }

            return [
                'access_decision' => IncomingMessageAccessDecision::ACCEPTED->value,
                'ignored_reason' => null,
                'route' => 'activation_attempt',
                'tenant_user' => $tenantUser,
                'tenant_id' => (int) $tenantUser->tenant_id,
                'should_reply' => true,
            ];
        }

        return [
            'access_decision' => IncomingMessageAccessDecision::ACCEPTED->value,
            'ignored_reason' => null,
            'route' => 'access_granted',
            'tenant_user' => $tenantUser,
            'tenant_id' => (int) $tenantUser->tenant_id,
            'should_reply' => false,
        ];
    }

    private function looksLikeActivationCommand(?string $messageText): bool
    {
        return preg_match('/^\s*akti[fv]\s+\S+\s*$/i', (string) $messageText) === 1;
    }

    private function ignored(IncomingMessageIgnoredReason $reason, ?object $tenantUser = null): array
    {
        return [
            'access_decision' => IncomingMessageAccessDecision::IGNORED->value,
            'ignored_reason' => $reason->value,
            'route' => 'ignored',
            'tenant_user' => $tenantUser,
            'tenant_id' => $tenantUser ? (int) $tenantUser->tenant_id : null,
            'should_reply' => false,
        ];
    }
}
