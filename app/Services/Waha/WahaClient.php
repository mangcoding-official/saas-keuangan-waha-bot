<?php

namespace App\Services\Waha;

use Illuminate\Http\Client\PendingRequest;
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
}
