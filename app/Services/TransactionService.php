<?php

namespace App\Services;

use App\Models\Transaction;

class TransactionService
{
    public function create(
        array $parsed,
        $rawMessageId,
        $telegramUserId,
        $message
    ) {
        return Transaction::create([

            'raw_message_id' => $rawMessageId,

            'telegram_user_id' => $telegramUserId,
            'type' =>
            $parsed['intent'] === 'add_income'
                ? 'income'
                : 'expense',

        'amount' =>
            $parsed['amount'] ?? 0,

        'category' =>
            $parsed['category'] ?? 'general',

        'note' =>
            $parsed['note'] ?? $message,

        'transaction_date' =>
            $parsed['transaction_date'] ?? now()
    ]);
}
}