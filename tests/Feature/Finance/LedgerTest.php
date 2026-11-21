<?php

namespace Tests\Feature\Finance;

use App\Models\PaymentTransaction;
use App\Models\Subscribers;
use App\Models\TotalBank;
use App\Models\TotalSafe;
use App\Modules\Finance\Application\Ledger;
use App\Modules\Finance\Application\Treasury;
use App\Modules\Finance\Domain\Enums\Account;
use App\Modules\Finance\Domain\Enums\Direction;
use App\Modules\Finance\Domain\Enums\TransactionType;
use App\Modules\Finance\Domain\Exceptions\InsufficientFunds;
use App\Modules\Finance\Domain\LedgerEntry;
use App\Modules\Shared\Domain\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TotalSafe::create(['amount' => 1000]);
        TotalBank::create(['amount' => 500]);
        Subscribers::factory()->create(['member_id' => 15]);
    }

    private function entry(Direction $direction, int $amount, ?Account $account = Account::Safe): LedgerEntry
    {
        return new LedgerEntry(TransactionType::Subscription, $direction, Money::pounds($amount), '2027', $account, memberId: 15);
    }

    public function test_a_deposit_is_recorded_and_credits_the_account(): void
    {
        $transaction = app(Ledger::class)->record($this->entry(Direction::In, 300));

        $this->assertSame('ايداع', $transaction->transaction_cat);
        $this->assertSame('safe', $transaction->account);
        $this->assertSame(1300, app(Treasury::class)->balance(Account::Safe)->toPounds());
    }

    public function test_a_withdrawal_debits_the_account(): void
    {
        app(Ledger::class)->record($this->entry(Direction::Out, 200, Account::Bank));

        $this->assertSame(300, app(Treasury::class)->balance(Account::Bank)->toPounds());
    }

    public function test_a_withdrawal_larger_than_the_balance_is_refused_and_nothing_is_written(): void
    {
        try {
            app(Ledger::class)->record($this->entry(Direction::Out, 5000));
            $this->fail('Expected InsufficientFunds');
        } catch (InsufficientFunds $e) {
            $this->assertSame(Account::Safe, $e->account);
        }

        $this->assertSame(0, PaymentTransaction::count());
        $this->assertSame(1000, app(Treasury::class)->balance(Account::Safe)->toPounds());
    }

    public function test_an_expense_may_overdraw_when_allowed(): void
    {
        app(Ledger::class)->record($this->entry(Direction::Out, 1200), allowOverdraft: true);

        $this->assertSame(-200, (int) TotalSafe::first()->amount);
    }

    public function test_an_online_payment_moves_no_balance(): void
    {
        app(Ledger::class)->record($this->entry(Direction::In, 100, account: null));

        $this->assertSame(1000, app(Treasury::class)->balance(Account::Safe)->toPounds());
        $this->assertNull(PaymentTransaction::sole()->account);
    }
}
