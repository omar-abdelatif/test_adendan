<?php

namespace Tests\Feature\Finance;

use App\Models\Donations;
use App\Models\Donators;
use App\Models\Due;
use App\Models\Miscellaneous;
use App\Models\OuterDonations;
use App\Models\PaymentTransaction;
use App\Models\Subscribers;
use App\Models\TotalBank;
use App\Models\TotalSafe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\ActsAsStaff;
use Tests\TestCase;

class OfficeFinanceTest extends TestCase
{
    use ActsAsStaff, RefreshDatabase;

    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();
        TotalSafe::create(['amount' => 1000]);
        TotalBank::create(['amount' => 0]);
    }

    protected function tearDown(): void
    {
        foreach (PaymentTransaction::whereNotNull('proof_img')->pluck('proof_img') as $path) {
            @unlink(public_path('assets/images/'.$path));
        }
        foreach (Miscellaneous::withoutGlobalScopes()->whereNotNull('invoice_img')->pluck('invoice_img') as $name) {
            @unlink(public_path('assets/images/miscellaneous/'.$name));
        }
        parent::tearDown();
    }

    private function safe(): int
    {
        return (int) TotalSafe::first()->amount;
    }

    private function image(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'img');
        imagepng(imagecreatetruecolor(5, 5), $path);

        return new UploadedFile($path, 'receipt.png', null, null, true);
    }

    public function test_partial_then_full_payment_of_a_subscription_due(): void
    {
        $this->actingAsStaff('المديونيات');
        $member = Subscribers::factory()->create();
        $due = Due::factory()->forMember($member)->amount(300)->create();
        $form = ['id' => $due->id, 'member_id' => $member->member_id, 'item' => $due->item, 'payment_method' => 'كاش', 'payment_date' => '2026-11-21'];

        $this->post('/admin/subscription/dues/pay', $form + ['amount_paid' => 100, 'inv_no' => 501])
            ->assertSessionHas('message', 'تم دفع جزء من المبلغ');
        $this->assertEquals(200, $due->fresh()->amount_remaining);

        // Paying more than what is left records only what was owed.
        $this->post('/admin/subscription/dues/pay', $form + ['amount_paid' => 500, 'inv_no' => 502])
            ->assertSessionHas('message', 'تم دفع كل المبلغ');

        $this->assertNull($due->fresh());
        $this->assertSame([100, 200], PaymentTransaction::orderBy('id')->pluck('amount')->all());
        $this->assertSame(['جزئي', 'كلي'], PaymentTransaction::orderBy('id')->pluck('payment_cat')->all());
        $this->assertSame(1300, $this->safe());
    }

    public function test_paying_a_donation_due_also_shows_in_the_donations_report(): void
    {
        $this->actingAsStaff('المديونيات');
        $member = Subscribers::factory()->create();
        $due = Due::factory()->forMember($member)->donation('تبرع سيارة التكريم')->amount(100)->create();

        $this->post('/admin/subscription/dues/pay', [
            'id' => $due->id, 'member_id' => $member->member_id, 'item' => $due->item,
            'amount_paid' => 100, 'inv_no' => 600, 'payment_method' => 'كاش',
        ])->assertSessionHasNoErrors();

        $this->assertSame('تبرعات', PaymentTransaction::sole()->transaction_type);
        $this->assertSame('تبرع سيارة التكريم', Donations::sole()->donation_category);
    }

    public function test_receipt_numbers_cannot_be_reused(): void
    {
        $this->actingAsStaff('المديونيات');
        PaymentTransaction::factory()->create(['inv_no' => 700]);
        $member = Subscribers::factory()->create();
        $due = Due::factory()->forMember($member)->create();

        $this->post('/admin/subscription/dues/pay', [
            'id' => $due->id, 'member_id' => $member->member_id, 'item' => $due->item,
            'amount_paid' => 50, 'inv_no' => 700, 'payment_method' => 'كاش',
        ])->assertSessionHasErrors('inv_no');
    }

    public function test_issuing_a_year_charges_active_members_once(): void
    {
        $this->actingAsStaff('المديونيات');
        Subscribers::factory()->count(3)->create();
        Subscribers::factory()->resigned()->create();

        $this->post('/admin/subscription/dues/upload_delays', ['item' => '2027', 'amount' => 360])->assertSessionHasNoErrors();
        $this->assertSame(3, Due::where('item', '2027')->count());

        $this->post('/admin/subscription/dues/upload_delays', ['item' => '2027', 'amount' => 360])->assertSessionHasErrors();
        $this->assertSame(3, Due::where('item', '2027')->count());
    }

    public function test_donation_campaign_uses_the_custom_name_for_other(): void
    {
        $this->actingAsStaff('التبرعات');
        Subscribers::factory()->count(2)->create();

        $this->post('/admin/donations/donations/delays/upload_bulk', [
            'donation_type' => 'أخرى', 'delay_other_amount' => 'ترميم المسجد', 'delay_amount' => 150,
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, Due::where('item', 'ترميم المسجد')->where('transaction_type', 'تبرعات')->count());
    }

    public function test_member_and_outside_donations_go_into_the_safe(): void
    {
        $this->actingAsStaff('التبرعات', 'التبرعات الخارجية');
        $member = Subscribers::factory()->create();
        $donor = Donators::create(['name' => 'متبرع', 'mobile_number' => 1011111111, 'donator_type' => 'فرد']);

        $this->post('/admin/donations/donations/store', [
            'member_id' => $member->member_id, 'amount' => 250, 'donation_type' => 'مادي', 'donation_category' => 'ص.مقر', 'invoice_no' => 900,
        ])->assertSessionHasNoErrors();

        $this->post('/admin/outer_donations/outer_donations/history/store', [
            'id' => $donor->id, 'amount' => 750, 'invoice_id' => 901, 'donation_destination' => 'المقر', 'payment_method' => 'كاش',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, Donations::count());
        $this->assertSame(1, OuterDonations::count());
        $this->assertSame(2000, $this->safe());
    }

    public function test_transfer_moves_cash_between_safe_and_bank(): void
    {
        $this->actingAsStaff('التقارير');

        $this->post('/admin/withdraw/withdraw', ['amount' => 400, 'proof_img' => $this->image(), 'transaction_date' => '2026-11-21'])
            ->assertSessionHasNoErrors();

        $this->assertSame(600, $this->safe());
        $this->assertSame(400, (int) TotalBank::first()->amount);
        $this->assertSame(2, PaymentTransaction::count());
        $this->assertStringStartsWith('withdraws/', PaymentTransaction::first()->proof_img);

        $this->post('/admin/withdraw/withdraw/bank', ['amount' => 5000, 'proof_img' => $this->image()])
            ->assertSessionHasErrors('amount');
        $this->assertSame(400, (int) TotalBank::first()->amount);
    }

    public function test_expense_edits_and_deletes_keep_the_safe_right(): void
    {
        $this->actingAsStaff('النثريات');

        $this->post('/admin/miscellaneous/miscellaneous/store', ['category' => 'كهرباء', 'amount' => 100])->assertSessionHasNoErrors();
        $expense = Miscellaneous::sole();
        $this->assertSame(900, $this->safe());

        $this->post('/admin/miscellaneous/miscellaneous/update', ['id' => $expense->id, 'category' => 'كهرباء', 'amount' => 150])
            ->assertSessionHasNoErrors();
        $this->assertSame(850, $this->safe());

        $this->get("/admin/miscellaneous/miscellaneous/delete/{$expense->id}")->assertSessionHasNoErrors();
        $this->assertSame(1000, $this->safe());
        $this->assertSame(3, PaymentTransaction::count());
    }

    public function test_finance_report_pages_render(): void
    {
        $this->actingAsStaff('التقارير', 'النثريات', 'الإشتراك السنوي', 'الاشتراكات');
        $member = Subscribers::factory()->create();
        Due::factory()->forMember($member)->create();
        PaymentTransaction::factory()->create(['member_id' => $member->member_id]);

        foreach (['reports/all', 'reports/safe', 'reports/bank', 'reports/search', 'reports/search?amount=100&optype=>', 'reports/donations', 'reports/inner_donations', 'miscellaneous/miscellaneous/all', 'costyears/costyears/all', "subscription/history/{$member->id}"] as $page) {
            $this->get('/admin/'.$page)->assertOk();
        }
    }
}
