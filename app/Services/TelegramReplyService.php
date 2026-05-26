<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class TelegramReplyService
{
    public function send($chatId, $text)
    {
        $botToken = env('TELEGRAM_BOT_TOKEN');

        return Http::post(
            "https://api.telegram.org/bot{$botToken}/sendMessage",
            [
                'chat_id' => $chatId,

                'text' => $text
            ]

        );
    }
}