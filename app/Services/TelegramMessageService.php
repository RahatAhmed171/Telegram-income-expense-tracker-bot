<?php

namespace App\Services;

use Illuminate\Http\Request;
use App\Models\TelegramMessage;

class TelegramMessageService
{
    public function extract(Request $request)
    {
        $data = $request->all();

        if (
            !isset($data['update_id']) ||
            !isset($data['message']) ||
            !isset($data['message']['text']) ||
            !isset($data['message']['chat']['id']) ||
            !isset($data['message']['from']['id'])
        ) {
            return [
                'valid' => false
            ];
        }

        return [
            'valid' => true,

            'update_id' =>
                $data['update_id'],

            'message' =>
                $data['message']['text'],

            'chat_id' =>
                $data['message']['chat']['id'],

            'telegram_user_id' =>
                $data['message']['from']['id']
        ];
    }

    public function storeRawMessage(
        $updateId,
        $telegramUserId,
        $chatId,
        $message
    ) {
        return TelegramMessage::create([
            'telegram_update_id' => $updateId,
            'telegram_user_id' => $telegramUserId,
            'chat_id' => $chatId,
            'message' => $message
        ]);
    }
}