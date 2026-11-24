<?php

namespace Tests\Feature\Finance;

use App\Models\Due;
use App\Models\PaymentTransaction;
use App\Models\SMSFEES;
use App\Models\SMSSubscribers;
use App\Models\Subscribers;
use App\Models\TotalSafe;
use App\Modules\Finance\Infrastructure\Paymob\PaymobGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OnlinePaymentTest extends TestCase
{
    use RefreshDatabase;

    private Subscribers $member;

    protected function setUp(): void
    {
        parent::setUp();
        config(['paymob.live_integration_ids' => ['1']]);
        TotalSafe::create(['amount' => 0]);
        SMSFEES::create(['item' => 'رسائل', 'amount' => 60]);
        $this->member = Subscribers::factory()->create(['member_id' => 15, 'mobile_no' => 1012345678]);
    }

    private function notifyPayment(string $merchantOrderId, int $cents, int $id = 5551)
    {
        $obj = [
            'id' => $id, 'amount_cents' => $cents, 'created_at' => '2026-11-21T10:00:00', 'currency' => 'EGP',
            'error_occured' => false, 'has_parent_transaction' => false, 'integration_id' => 1, 'is_3d_secure' => true,
            'is_auth' => false, 'is_capture' => false, 'is_refunded' => false, 'is_standalone_payment' => true,
            'is_voided' => false, 'order' => ['id' => 77, 'merchant_order_id' => $merchantOrderId], 'owner' => 1,
            'pending' => false, 'source_data' => ['pan' => '1234', 'sub_type' => 'wallet', 'type' => 'wallet'], 'success' => true,
        ];
        $hmac = hash_hmac('sha512', (new PaymobGateway([]))->signedString($obj), 'test-hmac-secret');

        return $this->postJson('/api/paymob/callback?hmac='.$hmac, ['type' => 'TRANSACTION', 'obj' => $obj]);
    }

    public function test_paid_sms_subscription_is_activated_only_by_the_callback(): void
    {
        $this->notifyPayment('15-رسائل-اشتراك-abc123-01012345678', 6000)->assertOk();

        $subscription = SMSSubscribers::sole();
        $this->assertSame(15, (int) $subscription->member_id);
        $this->assertSame(1, (int) $subscription->active_sms);
        $this->assertSame('اشتراك', PaymentTransaction::sole()->transaction_type);
        // Online money is held by the provider, not put in the safe.
        $this->assertSame(0, (int) TotalSafe::first()->amount);
    }

    public function test_paying_sms_again_updates_the_same_subscription_row(): void
    {
        $this->notifyPayment('15-رسائل-اشتراك-first1-01012345678', 6000, id: 1)->assertOk();
        $this->notifyPayment('15-رسائل-اشتراك-second-01012345678', 6000, id: 2)->assertOk();

        $this->assertSame(1, SMSSubscribers::count());
        $this->assertSame(2, PaymentTransaction::count());
    }

    public function test_due_reference_pays_that_exact_due(): void
    {
        $due = Due::factory()->forMember($this->member)->amount(300)->create(['item' => '2027']);
        Due::factory()->forMember($this->member)->amount(300)->create(['item' => '2026']);

        $this->notifyPayment("15-2027-إشتراك-d{$due->id}xrand01-01012345678", 10000)->assertOk();

        $this->assertEquals(200, $due->fresh()->amount_remaining);
        $this->assertEquals(300, Due::where('item', '2026')->value('amount_remaining'));
        $this->assertSame('online', PaymentTransaction::sole()->payment_method === 'wallet' ? 'online' : 'office');
    }

    public function test_money_received_without_a_matching_due_is_still_recorded(): void
    {
        $this->notifyPayment('15-2030-إشتراك-ref999-01012345678', 5000)->assertOk();

        $this->assertSame(50, PaymentTransaction::sole()->amount);
    }

    public function test_old_sms_endpoints_no_longer_activate_sms_for_free(): void
    {
        Http::fake();
        Sanctum::actingAs($this->member);

        $this->postJson('/api/sms/pay_subscription', ['payment_method' => 'wallet', 'item' => 'الرسائل'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('sms_status', null);
        $this->postJson('/api/sms/renew_subscription', ['payment_method' => 'wallet'])->assertOk();

        $this->assertSame(0, SMSSubscribers::count());
        $this->assertSame(0, PaymentTransaction::count());
        Http::assertNothingSent();
    }
}
