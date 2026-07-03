<?php

namespace App\Services;

use App\Models\ApplicationSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    /**
     * Get the GOWA API base URL.
     */
    public static function getApiUrl(): string
    {
        $url = null;
        try {
            $url = ApplicationSetting::query()->where('key', 'whatsapp_api_url')->value('value');
        } catch (\Throwable) {}
        return $url ? rtrim(trim($url), '/') : 'http://localhost:3000';
    }

    /**
     * Get Basic Auth Credentials.
     */
    public static function getAuthCredentials(): array
    {
        $user = '';
        $pass = '';
        try {
            $user = ApplicationSetting::query()->where('key', 'whatsapp_basic_auth_user')->value('value') ?: '';
            $pass = ApplicationSetting::query()->where('key', 'whatsapp_basic_auth_password')->value('value') ?: '';
        } catch (\Throwable) {}
        return [trim($user), trim($pass)];
    }

    /**
     * Get the webhook signature verification secret.
     */
    public static function getWebhookSecret(): string
    {
        $secret = '';
        try {
            $secret = ApplicationSetting::query()->where('key', 'whatsapp_webhook_secret')->value('value') ?: '';
        } catch (\Throwable) {}
        return $secret ?: 'secret';
    }

    /**
     * Build the HTTP client with optional basic auth.
     */
    private static function client()
    {
        $client = Http::timeout(10);
        [$user, $pass] = self::getAuthCredentials();
        if ($user !== '' || $pass !== '') {
            $client = $client->withBasicAuth($user, $pass);
        }
        return $client;
    }

    /**
     * Fetch all connected devices.
     */
    public static function getDevices(): array
    {
        try {
            $url = self::getApiUrl() . '/devices';
            $response = self::client()->get($url);

            if ($response->successful()) {
                $data = $response->json();
                $rawDevices = [];
                if (isset($data['results']) && is_array($data['results'])) {
                    $rawDevices = $data['results'];
                } elseif (is_array($data)) {
                    $rawDevices = $data;
                }

                $formatted = [];
                foreach ($rawDevices as $dev) {
                    $formatted[] = [
                        'device_id' => $dev['id'] ?? $dev['device_id'] ?? '',
                        'name' => $dev['display_name'] ?? $dev['name'] ?? '',
                        'jid' => $dev['jid'] ?? '',
                        'connected' => ($dev['state'] ?? '') === 'logged_in' || ($dev['connected'] ?? false),
                    ];
                }
                return $formatted;
            }
        } catch (\Throwable $e) {
            Log::error('WhatsAppService::getDevices failed: ' . $e->getMessage());
        }
        return [];
    }

    /**
     * Send a WhatsApp message.
     * 
     * @param string $deviceId Target device (JID)
     * @param string $phone Recipient phone number
     * @param string $message Text message
     */
    public static function sendMessage(string $deviceId, string $phone, string $message): array
    {
        try {
            $url = self::getApiUrl() . '/send/message';
            $jid = self::formatJid($phone);

            $response = self::client()
                ->withHeaders(['X-Device-Id' => $deviceId])
                ->post($url, [
                    'phone' => $jid,
                    'message' => $message,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'message_id' => $data['results']['id'] ?? $data['id'] ?? null,
                    'data' => $data,
                ];
            }

            return [
                'success' => false,
                'error' => 'API returned status ' . $response->status() . ': ' . $response->body(),
            ];
        } catch (\Throwable $e) {
            Log::error('WhatsAppService::sendMessage failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Format a raw phone number into a WhatsApp JID.
     */
    public static function formatJid(string $phone): string
    {
        $clean = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($clean, '0')) {
            $clean = '62' . substr($clean, 1);
        }
        if (!str_contains($phone, '@')) {
            return $clean . '@s.whatsapp.net';
        }
        return $phone;
    }

    /**
     * Clean WhatsApp JID back to a normal phone number (e.g. 628123456789).
     */
    public static function cleanJid(string $jid): string
    {
        return explode('@', $jid)[0];
    }

    /**
     * Fetch chats/conversations for a specific device.
     */
    public static function getChats(string $deviceId): array
    {
        try {
            $url = self::getApiUrl() . '/chats';
            $response = self::client()
                ->withHeaders(['X-Device-Id' => $deviceId])
                ->get($url);

            if ($response->successful()) {
                $data = $response->json();
                $results = $data['results'] ?? [];
                
                // GOWA returns: results => { data => [...], pagination => {...} }
                if (isset($results['data']) && is_array($results['data'])) {
                    return $results['data'];
                }
                
                // Fallback for older GOWA versions
                if (is_array($results)) {
                    return $results;
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('WhatsAppService::getChats failed: ' . $e->getMessage());
        }
        return [];
    }

    /**
     * Fetch message history for a specific chat JID on a device.
     */
    public static function getChatMessages(string $deviceId, string $jid): array
    {
        try {
            $url = self::getApiUrl() . '/chat/' . $jid . '/messages';
            $response = self::client()
                ->withHeaders(['X-Device-Id' => $deviceId])
                ->get($url);

            if ($response->successful()) {
                $data = $response->json();
                $results = $data['results'] ?? [];
                if (isset($results['data']) && is_array($results['data'])) {
                    return $results['data'];
                }
                if (is_array($results)) {
                    return $results;
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('WhatsAppService::getChatMessages failed: ' . $e->getMessage());
        }
        return [];
    }

    /**
     * Verify the incoming Webhook signature.
     */
    public static function verifyWebhookSignature(string $payload, ?string $signature): bool
    {
        if (!$signature) {
            return false;
        }

        $secret = self::getWebhookSecret();
        $expectedSignature = hash_hmac('sha256', $payload, $secret);
        
        $receivedSignature = str_replace('sha256=', '', $signature);
        
        return hash_equals($expectedSignature, $receivedSignature);
    }
}
