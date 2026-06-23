<?php

namespace App\Services\Waha;

use App\Enums\MessageChatType;
use App\Support\PhoneNumberNormalizer;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class WahaWebhookPayloadNormalizer
{
    public function __construct(
        private readonly PhoneNumberNormalizer $phoneNumberNormalizer,
        private readonly WahaClient $wahaClient,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     source_message_id: string,
     *     bot_instance_key: string,
     *     event_name: string,
     *     from_me: bool,
     *     chat_type: string,
     *     sender_raw: ?string,
     *     sender_normalized: ?string,
     *     chat_id: ?string,
     *     message_text: ?string,
     *     has_media: bool,
     *     media: ?array{url:string,mime_type:?string,file_name:?string,file_size:?int,width:?int,height:?int},
     *     message_timestamp: Carbon,
     *     raw_payload: array<string, mixed>
     * }
     */
    public function normalize(array $payload): array
    {
        $messagePayload = is_array($payload['payload'] ?? null) ? $payload['payload'] : [];
        $sessionKey = $this->stringOrDefault($payload['session'] ?? null, (string) config('services.waha.default_session'));
        $sourceMessageId = $this->stringOrEmpty($messagePayload['id'] ?? $payload['id'] ?? null);

        if ($sourceMessageId === '') {
            throw new InvalidArgumentException('Payload WAHA tidak memiliki source message id.');
        }

        $chatId = $this->nullableString($messagePayload['from'] ?? null);
        $senderRaw = $this->resolveSenderRaw(
            $this->nullableString($messagePayload['participant'] ?? null),
            $this->nullableString($messagePayload['author'] ?? null),
            $chatId,
        );

        return [
            'source_message_id' => $sourceMessageId,
            'bot_instance_key' => $sessionKey,
            'event_name' => $this->stringOrDefault($payload['event'] ?? null, 'message'),
            'from_me' => (bool) ($messagePayload['fromMe'] ?? false),
            'chat_type' => $this->detectChatType($chatId)->value,
            'sender_raw' => $senderRaw,
            'sender_normalized' => $this->normalizeSender($sessionKey, $senderRaw),
            'chat_id' => $chatId,
            'message_text' => $this->normalizeMessageText($messagePayload['body'] ?? null),
            'has_media' => (bool) ($messagePayload['hasMedia'] ?? false),
            'media' => $this->normalizeMedia($messagePayload),
            'message_timestamp' => $this->resolveTimestamp($messagePayload['timestamp'] ?? $payload['timestamp'] ?? null),
            'raw_payload' => $payload,
        ];
    }

    private function resolveSenderRaw(?string $participant, ?string $author, ?string $from): ?string
    {
        foreach ([$participant, $author, $from] as $candidate) {
            if ($candidate !== null && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }

    private function detectChatType(?string $chatId): MessageChatType
    {
        $value = strtolower(trim((string) $chatId));

        if ($value === '') {
            return MessageChatType::UNKNOWN;
        }

        if ($value === 'status@broadcast') {
            return MessageChatType::STATUS;
        }

        if (str_ends_with($value, '@g.us')) {
            return MessageChatType::GROUP;
        }

        if (str_ends_with($value, '@newsletter')) {
            return MessageChatType::CHANNEL;
        }

        if (str_ends_with($value, '@broadcast')) {
            return MessageChatType::BROADCAST;
        }

        if (str_ends_with($value, '@c.us') || str_ends_with($value, '@lid') || preg_match('/^\d+$/', $value) === 1) {
            return MessageChatType::PERSONAL;
        }

        return MessageChatType::UNKNOWN;
    }

    private function normalizeSender(string $sessionKey, ?string $senderRaw): ?string
    {
        $raw = strtolower(trim((string) $senderRaw));

        if ($raw === '' || $raw === 'status@broadcast') {
            return null;
        }

        $identifier = explode('@', $raw)[0] ?? '';
        $server = explode('@', $raw)[1] ?? '';

        if ($server === 'lid') {
            $resolvedNumber = $this->wahaClient->lookupContactNumber($sessionKey, $raw);

            if ($resolvedNumber === null) {
                return null;
            }

            $identifier = $resolvedNumber;
        }

        if ($identifier === '') {
            return null;
        }

        try {
            return $this->phoneNumberNormalizer->normalize($identifier);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private function normalizeMessageText(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    /**
     * @param  array<string, mixed>  $messagePayload
     * @return array{url:string,mime_type:?string,file_name:?string,file_size:?int,width:?int,height:?int}|null
     */
    private function normalizeMedia(array $messagePayload): ?array
    {
        if (! (bool) ($messagePayload['hasMedia'] ?? false)) {
            return null;
        }

        $media = is_array($messagePayload['media'] ?? null) ? $messagePayload['media'] : [];
        $url = $this->stringOrEmpty($media['url'] ?? null);

        if ($url === '') {
            return null;
        }

        return [
            'url' => $url,
            'mime_type' => $this->nullableString($media['mimetype'] ?? data_get($messagePayload, '_data.mimetype')),
            'file_name' => $this->nullableString($media['filename'] ?? data_get($messagePayload, '_data.filename')),
            'file_size' => $this->nullableInteger($messagePayload['size'] ?? data_get($messagePayload, '_data.size')),
            'width' => $this->nullableInteger($messagePayload['width'] ?? data_get($messagePayload, '_data.width')),
            'height' => $this->nullableInteger($messagePayload['height'] ?? data_get($messagePayload, '_data.height')),
        ];
    }

    private function resolveTimestamp(mixed $value): Carbon
    {
        if (is_numeric($value)) {
            $timestamp = (string) $value;

            return strlen($timestamp) > 10
                ? Carbon::createFromTimestampMsUTC((int) $timestamp)
                : Carbon::createFromTimestampUTC((int) $timestamp);
        }

        return now();
    }

    private function stringOrEmpty(mixed $value): string
    {
        return trim((string) $value);
    }

    private function stringOrDefault(mixed $value, string $default): string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : $default;
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function nullableInteger(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
