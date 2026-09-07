<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramReplyService
{
    public function send($chatId, $text)
    {
        $botToken = config('services.telegram.bot_token');
        $apiBaseUrl = rtrim(
            config('services.telegram.api_base_url'),
            '/'
        );

        try {
            $response = Http::timeout(10)->post(
                "{$apiBaseUrl}/bot{$botToken}/sendMessage",
                [
                    'chat_id' => $chatId,
                    'text' => $text
                ]
            );
        } catch (Throwable $e) {

            Log::error('Telegram API request failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        if ($response->failed()) {

            Log::error('Telegram API returned an error', [
                'status' => $response->status(),
            ]);

            return false;
        }

        $data = $response->json();

        if (
            !is_array($data) ||
            ($data['ok'] ?? false) !== true
        ) {
            Log::error('Telegram API returned an unsuccessful response');

            return false;
        }

        return true;
    }
}