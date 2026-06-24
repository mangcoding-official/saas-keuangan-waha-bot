<?php

namespace App\Services\Waha;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class WahaClient
{
    public function sendText(string $sessionKey, string $chatId, string $text): void
    {
        $session = trim($sessionKey);
        $target = trim($chatId);

        if ($session === '' || $target === '' || trim($text) === '') {
            throw new RuntimeException('Parameter kirim pesan WAHA tidak lengkap.');
        }

        $this->request()
            ->post('/api/sendText', [
                'session' => $session,
                'chatId' => $target,
                'text' => $text,
            ])
            ->throw();
    }

    public function lookupContactNumber(string $sessionKey, string $contactId): ?string
    {
        $session = trim($sessionKey);
        $contact = trim($contactId);

        if ($session === '' || $contact === '') {
            return null;
        }

        $response = $this->request()
            ->get('/api/'.rawurlencode($session).'/contacts/'.rawurlencode($contact));

        if ($response->failed()) {
            return null;
        }

        $number = trim((string) $response->json('number', ''));

        if ($number !== '') {
            return $number;
        }

        $resolvedId = trim((string) $response->json('id', ''));

        if ($resolvedId === '') {
            return null;
        }

        return Str::before($resolvedId, '@');
    }

    public function downloadMedia(string $mediaUrl): string
    {
        $url = parse_url(trim($mediaUrl));
        $path = (string) ($url['path'] ?? '');

        if (! str_starts_with($path, '/api/files/')) {
            throw new RuntimeException('URL media WAHA tidak valid.');
        }

        if (isset($url['query']) && $url['query'] !== '') {
            $path .= '?'.$url['query'];
        }

        return $this->request()
            ->withHeaders(['Accept' => '*/*'])
            ->timeout(20)
            ->get($path)
            ->throw()
            ->body();
    }

    /**
     * @return array<string, mixed>
     */
    public function restartSession(string $sessionKey): array
    {
        $session = trim($sessionKey);

        if ($session === '') {
            throw new RuntimeException('Session WAHA tidak valid.');
        }

        $response = $this->request()
            ->post('/api/sessions/'.rawurlencode($session).'/restart')
            ->throw();

        return $this->decodeJsonResponse($response);
    }

    /**
     * @return array<string, mixed>
     */
    public function getSessionInfo(string $sessionKey): array
    {
        $session = trim($sessionKey);

        if ($session === '') {
            throw new RuntimeException('Session WAHA tidak valid.');
        }

        $response = $this->request()
            ->get('/api/sessions/'.rawurlencode($session))
            ->throw();

        return $this->decodeJsonResponse($response);
    }

    /**
     * @return array{data_url:?string, payload:array<string, mixed>}
     */
    public function refreshQrCode(string $sessionKey): array
    {
        $session = trim($sessionKey);

        if ($session === '') {
            throw new RuntimeException('Session WAHA tidak valid.');
        }

        $response = $this->request()
            ->withHeaders(['Accept' => 'application/json'])
            ->get('/api/'.rawurlencode($session).'/auth/qr');

        if ($response->failed() && ! str_contains(strtolower((string) $response->header('Content-Type')), 'image/')) {
            $response->throw();
        }

        $payload = $this->decodeJsonResponse($response);
        $dataUrl = $this->extractQrDataUrl($response, $payload);

        return [
            'data_url' => $dataUrl,
            'payload' => $payload,
        ];
    }

    private function request(): PendingRequest
    {
        $baseUrl = rtrim((string) config('services.waha.base_url'), '/');
        $apiKey = trim((string) config('services.waha.api_key'));
        $username = trim((string) config('services.waha.dashboard_username'));
        $password = (string) config('services.waha.dashboard_password');

        if ($baseUrl === '' || $apiKey === '') {
            throw new RuntimeException('Konfigurasi WAHA belum lengkap pada file environment.');
        }

        $request = Http::baseUrl($baseUrl)
            ->acceptJson()
            ->asJson()
            ->timeout(10)
            ->withHeaders([
                'X-Api-Key' => $apiKey,
            ]);

        if ($username !== '' && $password !== '') {
            $request = $request->withBasicAuth($username, $password);
        }

        return $request;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonResponse(Response $response): array
    {
        $decoded = $response->json();

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractQrDataUrl(Response $response, array $payload): ?string
    {
        $contentType = strtolower((string) $response->header('Content-Type'));

        if (str_contains($contentType, 'image/')) {
            return 'data:'.($contentType ?: 'image/png').';base64,'.base64_encode($response->body());
        }

        $candidates = [
            $payload['qr'] ?? null,
            $payload['value'] ?? null,
            $payload['base64'] ?? null,
            $payload['data'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || trim($candidate) === '') {
                continue;
            }

            $value = trim($candidate);

            if (str_starts_with($value, 'data:image/')) {
                return $value;
            }

            return 'data:image/png;base64,'.$value;
        }

        return null;
    }
}
