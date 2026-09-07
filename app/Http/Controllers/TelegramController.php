<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Services\InputParserService;
use App\Services\FinanceQueryService;
use App\Services\TelegramMessageService;
use App\Services\TransactionService;
use App\Services\TelegramReplyService;

class TelegramController extends Controller
{
    public function handle(
        Request $request,
        InputParserService $inputParserService,
        FinanceQueryService $financeQueryService,
        TelegramMessageService $telegramMessageService,
        TransactionService $transactionService,
        TelegramReplyService $telegramReplyService
    ) {

        /*
        |--------------------------------------------------------------------------
        | Extract Telegram Data
        |--------------------------------------------------------------------------
        */
        
        $telegramData =
            $telegramMessageService->extract($request);

            if (!$telegramData['valid']) {
    return response()->json([
        'success' => true,
        'message' => 'Unsupported Telegram update.'
    ]);
}

            $updateId =
            $telegramData['update_id'];
        $message =
            $telegramData['message'];

        $chatId =
            $telegramData['chat_id'];

        $telegramUserId =
            $telegramData['telegram_user_id'];

        /*
        |--------------------------------------------------------------------------
        | Store Raw Message
        |--------------------------------------------------------------------------
        */

        $existingMessage = \App\Models\TelegramMessage::where(
    'telegram_update_id',
    $updateId
)->first();

if ($existingMessage) {
    return response()->json([
        'success' => true,
        'message' => 'Update already processed.'
    ]);
}

$rawMessage =
    $telegramMessageService->storeRawMessage(
        $updateId,
        $telegramUserId,
        $chatId,
        $message
    );

        /*
        |--------------------------------------------------------------------------
        | Parse User Input
        |--------------------------------------------------------------------------
        */

        $parsed =
            $inputParserService->parse($message);

        /*
        |--------------------------------------------------------------------------
        | Handle AI Parsing Error
        |--------------------------------------------------------------------------
        */

        if (isset($parsed['error'])) {

            $telegramReplyService->send(
                $chatId,
                'Could not understand message.'
            );

            return response()->json([
                'success' => false
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Transaction Flow
        |--------------------------------------------------------------------------
        */

        if (
            isset($parsed['intent']) &&
            in_array(
                $parsed['intent'],
                ['add_income', 'add_expense']
            )
        ) {

            $transactionService->create(
                $parsed,
                $rawMessage->id,
                $telegramUserId,
                $message
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Query Flow
        |--------------------------------------------------------------------------
        */

        $queryReply = null;

        if (
            in_array(
                $parsed['intent'],
                [
                    'get_income',
                    'get_expense',
                    'get_summary'
                ]
            )
        ) {

            $queryReply =
                $financeQueryService->handle(
                    $parsed,
                    $telegramUserId
                );
        }

       /**
 * |--------------------------------------------------------------------------
 * | Telegram Reply
 * |--------------------------------------------------------------------------
 */

$textReply = null;

if (
    in_array(
        $parsed['intent'],
        ['add_income', 'add_expense']
    )
) {
    $type =
        $parsed['intent'] === 'add_income'
            ? 'Income'
            : 'Expense';

    $emoji =
        $parsed['intent'] === 'add_income'
            ? '💰'
            : '💸';

    $amount =
        number_format($parsed['amount'] ?? 0);

    $category =
        ucfirst($parsed['category'] ?? 'general');

    $date =
        $parsed['transaction_date']
        ?? now()->toDateString();

    $textReply =
        "✅ {$type} added successfully!\n"
        . "{$emoji} {$amount} BDT — {$category}\n"
        . "📅 {$date}";
}

if ($queryReply !== null) {
    $textReply = $queryReply;
}

$telegramReplyService->send(
    $chatId,
    $textReply
);

return response()->json([
    'success' => true
]);
    }
}
