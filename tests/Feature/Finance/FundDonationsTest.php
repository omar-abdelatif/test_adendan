<?php

namespace Tests\Feature\Finance;

use App\Models\Donators;
use App\Models\FundDonation;
use App\Models\PaymentTransaction;
use App\Modules\Finance\Application\Treasury;
use App\Modules\Finance\Domain\Enums\Account;
use App\Modules\Finance\Domain\Enums\Fund;
use App\Modules\Shared\Domain\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\ActsAsStaff;
use Tests\TestCase;

class FundDonationsTest extends TestCase
{
    use ActsAsStaff, RefreshDatabase;

    private function donor(string $mobile = '01001112223'): Donators
    {
        return Donators::create(['name' => 'حسن عبد الله صالح', 'mobile_number' => $mobile, 'donator_type' => 'منتظم']);
    }

    private function balance(Account $account, Fund $fund): int
    {
        return app(Treasury::class)->balance($account, $fund)->toPounds();
    }

    private function give(Donators $donor, array $data = [], string $fund = 'sponsorship')
    {
        return $this->post("/admin/funds/{$fund}/donations", $data + [
            'donator_id' => $donor->id, 'amount' => 500, 'account' => 'safe', 'invoice_no' => '4100',
            'category' => $fund === 'sponsorship' ? 'صدقات' : null, 'donated_on' => '2026-11-20',
        ]);
    }

    public function test_a_donation_goes_into_its_own_fund(): void
    {
        $this->actingAsStaff('الكفالة');
        $donor = $this->donor();
        $generalSafe = $this->balance(Account::Safe, Fund::General);

        $this->give($donor, ['months' => ['أكتوبر', 'نوفمبر']])
            ->assertSessionHasNoErrors()->assertSessionHas('alert-type', 'success')->assertSessionHas('receipt');

        $donation = FundDonation::sole();
        $this->assertSame(['أكتوبر', 'نوفمبر'], $donation->months);
        $this->assertSame(500, $this->balance(Account::Safe, Fund::Sponsorship));
        $this->assertSame($generalSafe, $this->balance(Account::Safe, Fund::General));
        $entry = PaymentTransaction::findOrFail($donation->transaction_id);
        $this->assertSame('sponsorship', $entry->fund);
        $this->assertSame('ايداع', $entry->transaction_cat);
        // The association's screens never see it.
        $this->assertSame(0, PaymentTransaction::query()->inFund(Fund::General)->count());
    }

    public function test_a_bank_payment_goes_into_the_fund_bank(): void
    {
        $this->actingAsStaff('المقابر');
        $this->give($this->donor(), ['account' => 'bank', 'amount' => 3000], 'new_tombs')->assertSessionHas('alert-type', 'success');

        $this->assertSame(3000, $this->balance(Account::Bank, Fund::NewTombs));
        $this->assertSame(0, $this->balance(Account::Safe, Fund::NewTombs));
        $this->assertNull(FundDonation::sole()->category);
    }

    public function test_changing_the_amount_writes_a_correction_not_a_new_balance(): void
    {
        $this->actingAsStaff('الكفالة');
        $this->give($this->donor());
        $donation = FundDonation::sole();

        $this->put("/admin/funds/sponsorship/donations/{$donation->id}", [
            'amount' => 800, 'account' => 'safe', 'invoice_no' => '4100', 'category' => 'زكاة مال', 'donated_on' => '2026-11-20',
        ])->assertSessionHasNoErrors()->assertSessionHas('alert-type', 'success');

        $this->assertSame(800, $this->balance(Account::Safe, Fund::Sponsorship));
        $this->assertSame('زكاة مال', $donation->fresh()->category);
        $this->assertSame([500, 500, 800], PaymentTransaction::query()->inFund(Fund::Sponsorship)->orderBy('id')->pluck('amount')->all());
        $this->assertSame(['ايداع', 'سحب', 'ايداع'], PaymentTransaction::query()->inFund(Fund::Sponsorship)->orderBy('id')->pluck('transaction_cat')->all());

        // A change that leaves the money alone writes nothing.
        $this->put("/admin/funds/sponsorship/donations/{$donation->id}", [
            'amount' => 800, 'account' => 'safe', 'invoice_no' => '4100', 'category' => 'زكاة مال', 'donated_on' => '2026-11-20', 'notes' => 'اتصلح',
        ]);
        $this->assertSame(3, PaymentTransaction::query()->inFund(Fund::Sponsorship)->count());
    }

    public function test_removing_a_donation_takes_its_money_back_out(): void
    {
        $this->actingAsStaff('الكفالة');
        $this->give($this->donor());

        $this->delete('/admin/funds/sponsorship/donations/'.FundDonation::sole()->id)->assertSessionHas('alert-type', 'success');

        $this->assertSame(0, FundDonation::count());
        $this->assertSame(0, $this->balance(Account::Safe, Fund::Sponsorship));
        $this->assertSame(2, PaymentTransaction::query()->inFund(Fund::Sponsorship)->count());
    }

    public function test_a_removal_is_refused_when_the_money_already_left_the_safe(): void
    {
        $this->actingAsStaff('الكفالة', 'خزنة الكفالة');
        $this->give($this->donor());
        $this->post('/admin/funds/sponsorship/accounts/transfer', ['from' => 'safe', 'amount' => 500, 'proof_img' => UploadedFile::fake()->image('slip.jpg')])
            ->assertSessionHas('alert-type', 'success');
        $this->assertSame(500, $this->balance(Account::Bank, Fund::Sponsorship));

        $this->delete('/admin/funds/sponsorship/donations/'.FundDonation::sole()->id)->assertSessionHas('alert-type', 'error');

        $this->assertSame(1, FundDonation::count());
        $this->assertSame(0, $this->balance(Account::Safe, Fund::Sponsorship));
    }

    public function test_receipt_numbers_are_unique_inside_a_fund_only(): void
    {
        $this->actingAsStaff('الكفالة', 'المقابر');
        $donor = $this->donor();
        $this->give($donor)->assertSessionHasNoErrors();

        $this->give($donor)->assertSessionHasErrors('invoice_no');
        $this->give($donor, [], 'new_tombs')->assertSessionHasNoErrors();
        $this->assertSame(2, FundDonation::count());
    }

    public function test_each_fund_needs_its_own_permission(): void
    {
        $this->actingAsStaff('الكفالة');

        $this->get('/admin/funds/sponsorship/donors')->assertOk();
        $this->get('/admin/funds/new_tombs/donors')->assertForbidden();
        $this->get('/admin/funds/sponsorship/accounts')->assertForbidden();
        $this->post('/admin/funds/sponsorship/accounts/transfer', [])->assertForbidden();
        // The association's money has its own screens.
        $this->get('/admin/funds/general/donors')->assertNotFound();
        $this->get('/admin/funds/elsewhere/donors')->assertNotFound();
    }

    public function test_a_donation_is_reached_only_through_its_own_fund(): void
    {
        $this->actingAsStaff('الكفالة', 'المقابر');
        $this->give($this->donor());
        $donation = FundDonation::sole();

        $this->delete("/admin/funds/new_tombs/donations/{$donation->id}")->assertNotFound();
        $this->get("/admin/funds/new_tombs/donations/{$donation->id}/receipt")->assertNotFound();
        $this->assertSame(1, FundDonation::count());
    }

    public function test_the_fund_pages_render(): void
    {
        $this->actingAsStaff('الكفالة', 'خزنة الكفالة');
        $donor = $this->donor();
        $this->give($donor);
        $donation = FundDonation::sole();

        $this->get('/admin/funds/sponsorship/donors')->assertOk()->assertSee($donor->name);
        $this->get("/admin/funds/sponsorship/donors/{$donor->id}")->assertOk()->assertSee('صدقات');
        $this->get('/admin/funds/sponsorship/donations?month=2026-11')->assertOk()->assertSee('4100');
        $this->get("/admin/funds/sponsorship/donations/{$donation->id}/receipt")->assertOk()->assertSee('استلمنا مبلغ')->assertSee($donor->name);
        $this->get('/admin/funds/sponsorship/accounts?month=2026-11')->assertOk()->assertSee('رصيد الخزنة')->assertSee('تبرع الكفالة: '.$donor->name);
        $this->get('/admin/funds/sponsorship/accounts?month=bad')->assertOk();
        $this->get('/admin/funds/sponsorship/donations/export?month=2026-11')->assertOk()->assertDownload('donations-sponsorship-2026-11.xlsx');
        $this->get('/admin/funds/sponsorship/accounts/export?month=2026-11')->assertOk()->assertDownload('accounts-sponsorship-2026-11.xlsx');
    }

    public function test_a_transfer_stays_inside_the_fund(): void
    {
        $this->actingAsStaff('الكفالة', 'خزنة الكفالة');
        $this->give($this->donor(), ['amount' => 2000]);
        $generalBank = $this->balance(Account::Bank, Fund::General);

        $this->post('/admin/funds/sponsorship/accounts/transfer', ['from' => 'safe', 'amount' => 1500, 'proof_img' => UploadedFile::fake()->image('slip.jpg')])
            ->assertSessionHas('alert-type', 'success');
        $this->post('/admin/funds/sponsorship/accounts/transfer', ['from' => 'bank', 'amount' => 500, 'proof_img' => UploadedFile::fake()->image('slip.jpg')])
            ->assertSessionHas('alert-type', 'success');
        $this->post('/admin/funds/sponsorship/accounts/transfer', ['from' => 'bank', 'amount' => 5000, 'proof_img' => UploadedFile::fake()->image('slip.jpg')])
            ->assertSessionHas('alert-type', 'error');

        $this->assertSame(1000, $this->balance(Account::Safe, Fund::Sponsorship));
        $this->assertSame(1000, $this->balance(Account::Bank, Fund::Sponsorship));
        $this->assertSame($generalBank, $this->balance(Account::Bank, Fund::General));
    }

    public function test_donors_are_shared_and_kept_while_they_have_fund_donations(): void
    {
        $this->actingAsStaff('الكفالة', 'المتبرعين');
        $this->post('/admin/funds/sponsorship/donors', ['name' => 'نادية سيد كرار', 'mobile_number' => '01112223334', 'donator_type' => 'موسمي'])
            ->assertSessionHas('alert-type', 'success')->assertSessionHas('give_to');
        $donor = Donators::sole();

        // The same number is the same person, whichever page adds them.
        $this->post('/admin/funds/sponsorship/donors', ['name' => 'نادية', 'mobile_number' => '1112223334', 'donator_type' => 'موسمي'])->assertSessionHasErrors('mobile_number');
        $this->post('/admin/donators/donators/store', ['name' => 'نادية', 'mobile_number' => '01112223334', 'donator_type' => 'منتظم'])->assertSessionHasErrors('mobile_number');

        $this->give($donor);
        $this->delete("/admin/donators/donators/remove/{$donor->id}")->assertSessionHas('alert-type', 'error');
        $this->assertModelExists($donor);
    }

    public function test_the_donation_rules(): void
    {
        $this->actingAsStaff('الكفالة');
        $donor = $this->donor();

        $this->give($donor, ['category' => 'أخرى'])->assertSessionHasErrors('category_other');
        $this->give($donor, ['category' => 'هدية'])->assertSessionHasErrors('category');
        $this->give($donor, ['amount' => 0])->assertSessionHasErrors('amount');
        $this->give($donor, ['donated_on' => now()->addDay()->toDateString()])->assertSessionHasErrors('donated_on');
        $this->give($donor, ['months' => ['رمضان']])->assertSessionHasErrors('months.0');
        $this->assertSame(0, FundDonation::count());
        $this->assertSame(Money::zero()->toPounds(), $this->balance(Account::Safe, Fund::Sponsorship));
    }
}
