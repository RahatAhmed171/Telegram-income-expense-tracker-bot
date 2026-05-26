<?php

namespace App\Services;

use App\Models\Transaction;

class FinanceQueryService
{
    public function handle(array $parsed, $telegramUserId)
    {
        $intent = $parsed['intent'];

        $startDate = $parsed['start_date'] ?? now()->toDateString();

        $endDate = $parsed['end_date'] ?? now()->toDateString();

        /*
        |--------------------------------------------------------------------------
        | GET EXPENSES
        |--------------------------------------------------------------------------
        */

        if ($intent === 'get_expense') {

            $transactions = Transaction::where(
                    'telegram_user_id',
                    $telegramUserId
                )
                ->where('type', 'expense')
                ->whereBetween(
                    'transaction_date',
                    [$startDate, $endDate]
                )
                ->get();

            $total = $transactions->sum('amount');

            if ($transactions->isEmpty()) {

                return "No expenses found.";
            }

            $reply = "Expenses\n\n";

            foreach ($transactions as $transaction) {

                $reply .=
                    ucfirst($transaction->category)
                    . ': '
                    . $transaction->amount
                    . " BDT\n";
            }

            $reply .= "\nTotal: {$total} BDT";

            return $reply;
        }

        /*
        |--------------------------------------------------------------------------
        | GET INCOME
        |--------------------------------------------------------------------------
        */

        if ($intent === 'get_income') {

            $transactions = Transaction::where(
                    'telegram_user_id',
                    $telegramUserId
                )
                ->where('type', 'income')
                ->whereBetween(
                    'transaction_date',
                    [$startDate, $endDate]
                )
                ->get();

            $total = $transactions->sum('amount');

            if ($transactions->isEmpty()) {

                return "No income found.";
            }

            $reply = "Income\n\n";

            foreach ($transactions as $transaction) {

                $reply .=
                    ucfirst($transaction->category)
                    . ': '
                    . $transaction->amount
                    . " BDT\n";
            }

            $reply .= "\nTotal: {$total} BDT";

            return $reply;
        }

        /*
        |--------------------------------------------------------------------------
        | GET SUMMARY
        |--------------------------------------------------------------------------
        */

        if ($intent === 'get_summary') {

            $income = Transaction::where(
                    'telegram_user_id',
                    $telegramUserId
                )
                ->where('type', 'income')
                ->whereBetween(
                    'transaction_date',
                    [$startDate, $endDate]
                )
                ->sum('amount');

            $expense = Transaction::where(
                    'telegram_user_id',
                    $telegramUserId
                )
                ->where('type', 'expense')
                ->whereBetween(
                    'transaction_date',
                    [$startDate, $endDate]
                )
                ->sum('amount');

            $balance = $income - $expense;

            return
                "Summary\n\n"
                . "Income: {$income} BDT\n"
                . "Expense: {$expense} BDT\n"
                . "Balance: {$balance} BDT";
        }

        return "Unknown query.";
    }
}