<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Services\InputParserService;
use App\Services\FinanceQueryService;
use App\Services\TelegramMessageService;
use App\Services\TransactionService;
use App\Services\WordPressSyncService;
use App\Services\TelegramReplyService;

class TelegramController extends Controller
{
    public function handle(
        Request $request,
        InputParserService $inputParserService,
        FinanceQueryService $financeQueryService,
        TelegramMessageService $telegramMessageService,
        TransactionService $transactionService,
        WordPressSyncService $wordPressSyncService,
        TelegramReplyService $telegramReplyService
    ) {

        /*
        |--------------------------------------------------------------------------
        | Extract Telegram Data
        |--------------------------------------------------------------------------
        */

        $telegramData =
            $telegramMessageService->extract($request);

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

        $rawMessage =
            $telegramMessageService->storeRawMessage(
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

            $wordPressSyncService->sync(
                $parsed,
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

        /*
        |--------------------------------------------------------------------------
        | Telegram Reply
        |--------------------------------------------------------------------------
        */

        $textReply =
            $queryReply
            ?? json_encode($parsed, JSON_PRETTY_PRINT);

        $telegramReplyService->send(
            $chatId,
            $textReply
        );

        return response()->json([
            'success' => true
        ]);
    }
}
