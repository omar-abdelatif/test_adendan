<?php

namespace Tests\Feature\Messaging;

use App\Models\PaymentTransaction;
use App\Models\Reminders;
use App\Models\SMSFEES;
use App\Models\SMSMSGS;
use App\Models\SMSSubscribers;
use App\Models\Subscribers;
use App\Models\TotalSafe;
use App\Modules\Messaging\Application\SmsSubscriptions;
use App\Modules\Messaging\Domain\SmsSender;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\ActsAsStaff;
use Tests\Fakes\FakeSmsSender;
use Tests\TestCase;

class SmsServiceTest extends TestCase
{
    use ActsAsStaff, RefreshDatabase;

    private FakeSmsSender $sms;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sms = new FakeSmsSender;
        $this->app->instance(SmsSender::class, $this->sms);
        TotalSafe::create(['amount' => 0]);
        SMSFEES::create(['item' => 'رسائل', 'amount' => 60]);
    }

    public function test_office_subscription_is_cash_in_the_safe(): void
    {
        $this->actingAsStaff('الرسائل');
        $member = Subscribers::factory()->create();

        $this->post('/admin/sms/store_subscriber', ['member_id' => $member->member_id, 'mobile_no' => '0'.$member->mobile_no])
            ->assertSessionHasNoErrors();
        // A non-member gives only a number.
        $this->post('/admin/sms/store_subscriber', ['mobile_no' => '01122223333'])->assertSessionHasNoErrors();

        $this->assertSame(2, SMSSubscribers::where('active_sms', 1)->count());
        $this->assertSame(1, SMSSubscribers::whereNull('member_id')->count());
        $this->assertSame(120, (int) TotalSafe::first()->amount);
        $this->assertSame(2, PaymentTransaction::where('item', 'رسائل')->where('account', 'safe')->count());
    }

    public function test_renewing_an_active_subscription_buys_the_next_season(): void
    {
        $this->actingAsStaff('الرسائل');
        $subscription = SMSSubscribers::factory()->create([
            'subscription_expiry_date' => app(SmsSubscriptions::class)->seasonEnd(now())->toDateString(),
        ]);
        $before = CarbonImmutable::parse($subscription->subscription_expiry_date);

        $this->putJson("/admin/sms/renew_sub/{$subscription->id}")->assertOk();

        $this->assertSame($before->addYear()->toDateString(), $subscription->fresh()->subscription_expiry_date);
        $this->assertSame('تجديد', PaymentTransaction::sole()->transaction_type);
    }

    public function test_announcement_goes_to_active_subscribers_only_and_checks_balance(): void
    {
        $this->actingAsStaff('الرسائل');
        SMSSubscribers::factory()->create(['mobile_no' => 1011111111]);
        SMSSubscribers::factory()->create(['mobile_no' => 1022222222, 'active_sms' => 0]);

        $this->postJson('/admin/sms/send_msg', ['content' => 'اجتماع الجمعية يوم الجمعة'])->assertOk()->assertJson(['success_count' => 1]);
        $this->assertSame(['1011111111'], $this->sms->numbers());
        $this->assertSame(1, SMSMSGS::count());

        $this->sms->balance = 0;
        $this->postJson('/admin/sms/send_msg', ['content' => 'رسالة'])->assertStatus(422);
    }

    public function test_nightly_reminders_and_deactivation(): void
    {
        $today = CarbonImmutable::parse('2027-05-30');
        CarbonImmutable::setTestNow($today);
        $monthAhead = SMSSubscribers::factory()->create(['subscription_expiry_date' => '2027-06-30']);
        $weekAhead = SMSSubscribers::factory()->create(['subscription_expiry_date' => '2027-06-06']);
        $today_ = SMSSubscribers::factory()->create(['subscription_expiry_date' => '2027-05-30']);
        $pastGrace = SMSSubscribers::factory()->create(['subscription_expiry_date' => '2027-05-01']);

        foreach (['sms:notify-expiry-month', 'sms:notify-expiry-upcoming', 'sms:notify-expiry-today', 'subscriptions:check-expiry'] as $command) {
            $this->artisan($command)->assertSuccessful();
        }

        // Each member is texted once, by the reminder that applies to them.
        $this->assertEqualsCanonicalizing(
            [(string) $monthAhead->mobile_no, (string) $weekAhead->mobile_no, (string) $today_->mobile_no],
            $this->sms->numbers(),
        );
        $this->assertEqualsCanonicalizing(['month_before', 'week_before', 'expiry_day'], Reminders::pluck('type')->all());
        // The expiry-day message comes while the grace period is still running.
        $this->assertSame(1, (int) $today_->fresh()->active_sms);
        $this->assertSame(0, (int) $pastGrace->fresh()->active_sms);
        CarbonImmutable::setTestNow();
    }

    public function test_member_reads_sms_status_and_fee_and_registers_device(): void
    {
        $member = Subscribers::factory()->create();
        Sanctum::actingAs($member);

        $this->getJson('/api/sms/sms_status')->assertNotFound();
        SMSSubscribers::factory()->create(['member_id' => $member->member_id]);
        $this->getJson('/api/sms/sms_status')->assertOk()->assertJsonPath('sms_status.status', 1);
        $this->getJson('/api/sms/fees')->assertOk()->assertJson(['fees' => 60]);
        $this->postJson('/api/fcm/notify/fcm-token', ['fcm_token' => 'device-token-1'])->assertOk();

        $this->assertSame('device-token-1', $member->fresh()->fcm_token);
    }

    public function test_sms_pages_render(): void
    {
        $this->actingAsStaff('الرسائل');
        SMSSubscribers::factory()->create();

        $this->get('/admin/sms/sms')->assertOk();
        $this->get('/admin/sms/create_new')->assertOk();
        $this->getJson('/admin/sms/balance')->assertOk()->assertJson(['balance' => 1000]);
    }
}
