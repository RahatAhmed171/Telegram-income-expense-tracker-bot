<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Services\FinanceQueryService;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_expense_transaction_is_created_correctly(): void
    {
        $service = new TransactionService();

        $transaction = $service->create(
            [
                'intent' => 'add_expense',
                'amount' => 500,
                'category' => 'food',
                'note' => 'Lunch',
                'transaction_date' => '2026-08-05'
            ],
            null,
            12345,
            'spent 500 on lunch'
        );

        $this->assertDatabaseHas('transactions', [
            'telegram_user_id' => 12345,
            'type' => 'expense',
            'amount' => 500,
            'category' => 'food',
            'transaction_date' => '2026-08-05'
        ]);
    }

    public function test_income_transaction_is_created_correctly(): void
    {
        $service = new TransactionService();

        $service->create(
            [
                'intent' => 'add_income',
                'amount' => 5000,
                'category' => 'salary',
                'note' => 'Monthly salary',
                'transaction_date' => '2026-08-01'
            ],
            null,
            12345,
            'received 5000 salary'
        );

        $this->assertDatabaseHas('transactions', [
            'telegram_user_id' => 12345,
            'type' => 'income',
            'amount' => 5000,
            'category' => 'salary',
            'transaction_date' => '2026-08-01'
        ]);
    }

    public function test_expense_query_returns_only_matching_user_type_and_date_range(): void
    {
        Transaction::create([
            'telegram_user_id' => 12345,
            'type' => 'expense',
            'amount' => 500,
            'category' => 'food',
            'transaction_date' => '2026-08-05'
        ]);

        Transaction::create([
            'telegram_user_id' => 12345,
            'type' => 'expense',
            'amount' => 300,
            'category' => 'transport',
            'transaction_date' => '2026-08-10'
        ]);

        // Outside date range
        Transaction::create([
            'telegram_user_id' => 12345,
            'type' => 'expense',
            'amount' => 1000,
            'category' => 'shopping',
            'transaction_date' => '2026-08-20'
        ]);

        // Different user
        Transaction::create([
            'telegram_user_id' => 99999,
            'type' => 'expense',
            'amount' => 700,
            'category' => 'food',
            'transaction_date' => '2026-08-05'
        ]);

        $service = new FinanceQueryService();

        $result = $service->handle(
            [
                'intent' => 'get_expense',
                'start_date' => '2026-08-01',
                'end_date' => '2026-08-10'
            ],
            12345
        );

        $this->assertStringContainsString('500', $result);
        $this->assertStringContainsString('300', $result);
        $this->assertStringContainsString('Total: 800 BDT', $result);

        $this->assertStringNotContainsString('1000', $result);
        $this->assertStringNotContainsString('700', $result);
    }

    public function test_income_query_returns_correct_income(): void
    {
        Transaction::create([
            'telegram_user_id' => 12345,
            'type' => 'income',
            'amount' => 5000,
            'category' => 'salary',
            'transaction_date' => '2026-08-05'
        ]);

        Transaction::create([
            'telegram_user_id' => 12345,
            'type' => 'income',
            'amount' => 2000,
            'category' => 'freelance',
            'transaction_date' => '2026-08-07'
        ]);

        $service = new FinanceQueryService();

        $result = $service->handle(
            [
                'intent' => 'get_income',
                'start_date' => '2026-08-01',
                'end_date' => '2026-08-10'
            ],
            12345
        );

        $this->assertStringContainsString('5000', $result);
        $this->assertStringContainsString('2000', $result);
        $this->assertStringContainsString('Total: 7000 BDT', $result);
    }

    public function test_summary_calculates_balance_correctly(): void
    {
        Transaction::create([
            'telegram_user_id' => 12345,
            'type' => 'income',
            'amount' => 10000,
            'category' => 'salary',
            'transaction_date' => '2026-08-05'
        ]);

        Transaction::create([
            'telegram_user_id' => 12345,
            'type' => 'expense',
            'amount' => 3000,
            'category' => 'food',
            'transaction_date' => '2026-08-06'
        ]);

        $service = new FinanceQueryService();

        $result = $service->handle(
            [
                'intent' => 'get_summary',
                'start_date' => '2026-08-01',
                'end_date' => '2026-08-10'
            ],
            12345
        );

        $this->assertStringContainsString('Income: 10000 BDT', $result);
        $this->assertStringContainsString('Expense: 3000 BDT', $result);
        $this->assertStringContainsString('Balance: 7000 BDT', $result);
    }
}