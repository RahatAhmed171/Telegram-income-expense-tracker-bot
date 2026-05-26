<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class WordPressSyncService
{
    public function sync(
        array $parsed,
        $telegramUserId,
        $message
    ) {
        return Http::post(
            'http://localhost/expense-income-tracker-wp/wordpress/wp-json/finance/v1/transaction',
            [
                'telegram_user_id' =>
                    $telegramUserId,

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
            ]
        );
    }
}