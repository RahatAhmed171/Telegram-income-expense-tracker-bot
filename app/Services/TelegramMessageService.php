<?php

namespace App\Services;

use Illuminate\Http\Request;
use App\Models\TelegramMessage;

class TelegramMessageService
{
    public function extract(Request $request)
    {
        $data = $request->all();

        return [
            'message' => $data['message']['text'] ?? '',

            'chat_id' =>
                $data['message']['chat']['id'] ?? null,
                'telegram_user_id' =>
                $data['message']['from']['id'] ?? null
        ];
    }

    public function storeRawMessage(
        $telegramUserId,
        $chatId,
        $message
    ) {
        return TelegramMessage::create([
            'telegram_user_id' => $telegramUserId,

            'chat_id' => $chatId,

            'message' => $message
        ]);
    }
}