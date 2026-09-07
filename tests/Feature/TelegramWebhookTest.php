<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_telegram_update_is_not_processed_twice(): void
    {
        Http::fake(function ($request) {

            if (
                str_contains(
                    $request->url(),
                    'generativelanguage.googleapis.com'
                )
            ) {
                return Http::response([
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [
                                    [
                                        'text' => json_encode([
                                            'intent' => 'add_expense',
                                            'amount' => 500,
                                            'category' => 'food',
                                            'note' => 'spent 500 on food',
                                            'start_date' => null,
                                            'end_date' => null,
                                            'transaction_date' => '2026-09-03'
                                        ])
                                    ]
                                ]
                            ]
                        ]
                    ]
                ], 200);
            }

            if (
                str_contains(
                    $request->url(),
                    'api.telegram.org'
                )
            ) {
                return Http::response([
                    'ok' => true,
                    'result' => []
                ], 200);
            }

            return Http::response([], 500);
        });

        $payload = [
            'update_id' => 1001,

            'message' => [
                'text' => 'spent 500 on food',

                'chat' => [
                    'id' => 12345
                ],

                'from' => [
                    'id' => 12345
                ]
            ]
        ];

        // First request
        $firstResponse = $this->postJson(
            '/api/telegram/webhook',
            $payload
        );

        $firstResponse
            ->assertStatus(200)
            ->assertJson([
                'success' => true
            ]);

        // Same Telegram update again
        $secondResponse = $this->postJson(
            '/api/telegram/webhook',
            $payload
        );

        $secondResponse
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Update already processed.'
            ]);

        // Only one message should exist
        $this->assertDatabaseCount(
            'telegram_messages',
            1
        );

        // Only one transaction should exist
        $this->assertDatabaseCount(
            'transactions',
            1
        );
    }
}