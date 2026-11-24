<?php

namespace Tests\Feature\Finance;

use App\Models\PaymentTransaction;
use App\Models\TotalBank;
use App\Models\TotalSafe;
use App\Modules\Access\Application\DashboardOverview;
use App\Modules\Finance\Application\Actions\TransferFunds;
use App\Modules\Finance\Application\Ledger;
use App\Modules\Finance\Application\Queries\FinanceReports;
use App\Modules\Finance\Application\Treasury;
use App\Modules\Finance\Domain\Enums\Account;
use App\Modules\Finance\Domain\Enums\Direction;
use App\Modules\Finance\Domain\Enums\Fund;
use App\Modules\Finance\Domain\Enums\TransactionType;
use App\Modules\Finance\Domain\Exceptions\InsufficientFunds;
use App\Modules\Finance\Domain\LedgerEntry;
use App\Modules\Shared\Domain\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** Sponsorship and new-tomb money lives in its own fund and never touches the association's totals. */
class FundsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TotalSafe::create(['amount' => 1000]);
        TotalBank::create(['amount' => 500]);
    }

    protected function tearDown(): void
    {
        foreach (PaymentTransaction::whereNotNull('proof_img')->pluck('proof_img')->unique() as $path) {
            @unlink(public_path('assets/images/'.$path));
        }
        parent::tearDown();
    }

    private function donation(int $amount, Fund $fund, Direction $direction = Direction::In, Account $account = Account::Safe): PaymentTransaction
    {
        return app(Ledger::class)->record(new LedgerEntry(
            TransactionType::OuterDonation, $direction, Money::pounds($amount), 'تبرع', $account, fund: $fund,
        ));
    }

    public function test_money_goes_to_its_own_fund(): void
    {
        $transaction = $this->donation(700, Fund::Sponsorship);

        $treasury = app(Treasury::class);
        $this->assertSame('sponsorship', $transaction->fund);
        $this->assertSame(700, $treasury->balance(Account::Safe, Fund::Sponsorship)->toPounds());
        $this->assertSame(0, $treasury->balance(Account::Safe, Fund::NewTombs)->toPounds());
        // The association's safe is the row it always was, untouched.
        $this->assertSame(1000, $treasury->balance(Account::Safe)->toPounds());
        $this->assertSame(1000, (int) TotalSafe::orderBy('id')->first()->amount);
    }

    public function test_a_fund_cannot_spend_another_funds_money(): void
    {
        $this->donation(200, Fund::NewTombs);

        try {
            $this->donation(300, Fund::NewTombs, Direction::Out);
            $this->fail('Expected InsufficientFunds');
        } catch (InsufficientFunds $e) {
            $this->assertSame(Fund::NewTombs, $e->fund);
            $this->assertStringContainsString('المقابر الجديدة', $e->getMessage());
        }

        $this->assertSame(200, app(Treasury::class)->balance(Account::Safe, Fund::NewTombs)->toPounds());
        $this->assertSame(1000, app(Treasury::class)->balance(Account::Safe)->toPounds());
    }

    public function test_a_transfer_stays_inside_its_fund(): void
    {
        $this->donation(900, Fund::Sponsorship);

        app(TransferFunds::class)->handle(Account::Safe, Account::Bank, Money::pounds(400), UploadedFile::fake()->image('proof.jpg'), now(), Fund::Sponsorship);

        $treasury = app(Treasury::class);
        $this->assertSame(500, $treasury->balance(Account::Safe, Fund::Sponsorship)->toPounds());
        $this->assertSame(400, $treasury->balance(Account::Bank, Fund::Sponsorship)->toPounds());
        $this->assertSame(1000, $treasury->balance(Account::Safe)->toPounds());
        $this->assertSame(500, $treasury->balance(Account::Bank)->toPounds());
        $this->assertSame(2, PaymentTransaction::inFund(Fund::Sponsorship)->where('transaction_type', TransactionType::Transfer->value)->count());
        $this->assertStringContainsString('الكفالة', PaymentTransaction::latest('id')->first()->item);
    }

    public function test_the_associations_screens_ignore_other_funds(): void
    {
        $this->donation(150, Fund::General);
        $this->donation(5000, Fund::Sponsorship);
        $this->donation(3000, Fund::NewTombs, account: Account::Bank);

        $overview = app(DashboardOverview::class);
        $this->assertSame(150, $overview->balances()['today']);
        $this->assertSame(1150, $overview->balances()['safe']);
        $this->assertSame(500, $overview->balances()['bank']);
        $this->assertSame(150, collect($overview->monthlyIncome()['months'])->sum('amount'));
        $this->assertCount(1, $overview->recentTransactions());

        $reports = app(FinanceReports::class);
        $this->assertCount(1, $reports->safe()['transactions']);
        $this->assertSame(1150, $reports->safe()['netSafeAmount']);
        $this->assertCount(0, $reports->bank()['transactions']);
    }

    public function test_existing_rows_belong_to_the_association(): void
    {
        $this->assertSame('general', TotalSafe::sole()->fund);
        $this->assertSame(Fund::General->value, $this->donation(10, Fund::General)->fund);
    }
}
