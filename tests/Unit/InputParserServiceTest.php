<?php

namespace Tests\Unit;

use App\Services\InputParserService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InputParserServiceTest extends TestCase
{
    public function test_valid_expense_response_is_accepted(): void
    {
        Http::fake([
            '*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'intent' => 'add_expense',
                                        'amount' => 500,
                                        'category' => 'food',
                                        'note' => 'Lunch',
                                        'start_date' => null,
                                        'end_date' => null,
                                        'transaction_date' => '2026-09-03'
                                    ])
                                ]
                            ]
                        ]
                    ]
                ]
            ], 200)
        ]);

        $service = new InputParserService();

        $result = $service->parse('spent 500 on lunch');

        $this->assertFalse($result['error'] ?? false);
        $this->assertEquals('add_expense', $result['intent']);
        $this->assertEquals(500, $result['amount']);
    }

    public function test_missing_amount_for_expense_is_rejected(): void
    {
        Http::fake([
            '*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'intent' => 'add_expense',
                                        'amount' => null,
                                        'category' => 'food',
                                        'note' => 'Lunch',
                                        'transaction_date' => '2026-09-03'
                                    ])
                                ]
                            ]
                        ]
                    ]
                ]
            ], 200)
        ]);

        $service = new InputParserService();

        $result = $service->parse('spent some money on lunch');

        $this->assertTrue($result['error']);
    }

    public function test_negative_amount_is_rejected(): void
    {
        Http::fake([
            '*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'intent' => 'add_expense',
                                        'amount' => -500,
                                        'category' => 'food',
                                        'note' => 'Lunch',
                                        'transaction_date' => '2026-09-03'
                                    ])
                                ]
                            ]
                        ]
                    ]
                ]
            ], 200)
        ]);

        $service = new InputParserService();

        $result = $service->parse('spent -500 on lunch');

        $this->assertTrue($result['error']);
    }

    public function test_invalid_intent_is_rejected(): void
    {
        Http::fake([
            '*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'intent' => 'delete_transaction',
                                        'amount' => 500,
                                        'category' => 'food',
                                        'transaction_date' => '2026-09-03'
                                    ])
                                ]
                            ]
                        ]
                    ]
                ]
            ], 200)
        ]);

        $service = new InputParserService();

        $result = $service->parse('delete my expense');

        $this->assertTrue($result['error']);
    }

    public function test_invalid_date_is_rejected(): void
    {
        Http::fake([
            '*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'intent' => 'add_expense',
                                        'amount' => 500,
                                        'category' => 'food',
                                        'note' => 'Lunch',
                                        'transaction_date' => 'not-a-date'
                                    ])
                                ]
                            ]
                        ]
                    ]
                ]
            ], 200)
        ]);

        $service = new InputParserService();

        $result = $service->parse('spent 500 on lunch');

        $this->assertTrue($result['error']);
    }
}