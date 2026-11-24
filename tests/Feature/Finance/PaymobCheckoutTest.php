<?php

namespace Tests\Feature\Finance;

use App\Models\Donations;
use App\Models\Due;
use App\Models\PaymentTransaction;
use App\Models\PaymobPayment;
use App\Models\SMSFEES;
use App\Models\SMSSubscribers;
use App\Models\Subscribers;
use App\Models\TotalSafe;
use App\Modules\Finance\Infrastructure\Paymob\PaymobGateway;
use App\Modules\Messaging\Listeners\NotifyMemberOfPayment;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Server-side Paymob checkout: the contract in the app repo's
 * specs/003-clean-architecture/laravel-payment-requirements.md (section 6).
 */
class PaymobCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private const LIVE_CARD = '4991596';

    private Subscribers $member;

    private int $intentions = 0;

    private bool $paymobDown = false;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'paymob.secret_key' => 'sk_test',
            'paymob.public_key' => 'pk_test',
            'paymob.card_integration_id' => self::LIVE_CARD,
            'paymob.wallet_integration_id' => '4991600',
            'paymob.live_integration_ids' => [self::LIVE_CARD, '4991600'],
        ]);

        TotalSafe::create(['amount' => 0]);
        SMSFEES::create(['item' => 'رسائل', 'amount' => 60]);
        $this->member = Subscribers::factory()->create(['member_id' => 15, 'mobile_no' => 1012345678, 'fcm_token' => 'device-token']);

        Http::fake(['accept.paymob.com/v1/intention/*' => function () {
            if ($this->paymobDown) {
                return Http::response(['detail' => 'error'], 500);
            }
            $this->intentions++;

            return Http::response(['client_secret' => 'cs_test_'.$this->intentions, 'intention_order_id' => 9000 + $this->intentions], 201);
        }]);
    }

    private function subscriptionDue(int $total = 300, int $paid = 0, string $item = '2026', ?Subscribers $member = null): Due
    {
        return Due::factory()->forMember($member ?? $this->member)->amount($total, $paid)
            ->create(['item' => $item, 'transaction_type' => 'إشتراك']);
    }

    private function checkout(array $body, ?string $key = null): TestResponse
    {
        Sanctum::actingAs($this->member);

        return $this->postJson('/api/payments/checkout', $body + ['method' => 'card'], [
            'Idempotency-Key' => $key ?? bin2hex(random_bytes(16)),
        ]);
    }

    /** A Paymob "transaction processed" callback for a checkout, signed with the test secret. */
    private function webhook(PaymobPayment $payment, array $overrides = [], ?string $hmac = null): TestResponse
    {
        $obj = array_replace_recursive([
            'id' => 700001,
            'amount_cents' => $payment->amount_cents,
            'created_at' => '2026-11-24T14:52:10.000000',
            'currency' => 'EGP',
            'error_occured' => false,
            'has_parent_transaction' => false,
            'integration_id' => (int) self::LIVE_CARD,
            'is_3d_secure' => true,
            'is_auth' => false,
            'is_capture' => false,
            'is_refunded' => false,
            'is_standalone_payment' => true,
            'is_voided' => false,
            'order' => ['id' => (int) $payment->paymob_order_id, 'merchant_order_id' => $payment->public_id],
            'owner' => 123,
            'pending' => false,
            'source_data' => ['pan' => '2346', 'sub_type' => 'MasterCard', 'type' => 'card'],
            'success' => true,
        ], $overrides);

        $hmac ??= hash_hmac('sha512', (new PaymobGateway([]))->signedString($obj), 'test-hmac-secret');

        return $this->postJson('/api/payments/paymob/webhook?hmac='.$hmac, ['type' => 'TRANSACTION', 'obj' => $obj]);
    }

    private function openCheckout(array $body): PaymobPayment
    {
        $id = $this->checkout($body)->assertCreated()->json('payment_id');

        return PaymobPayment::where('public_id', $id)->sole();
    }

    // 1
    public function test_valid_checkout_opens_one_pending_payment(): void
    {
        $this->subscriptionDue();

        $response = $this->checkout(['purpose' => 'subscription', 'reference' => '2026', 'amount' => 150.0])
            ->assertCreated()
            ->assertJsonStructure(['payment_id', 'checkout_url', 'expires_at']);

        $payment = PaymobPayment::sole();
        $this->assertSame('pending', $payment->status->value);
        $this->assertSame(15000, $payment->amount_cents);
        $this->assertSame(26, strlen($response->json('payment_id')));
        $this->assertStringContainsString('clientSecret=cs_test_1', $response->json('checkout_url'));
        $this->assertMatchesRegularExpression('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ$/', $response->json('expires_at'));
        $this->assertSame(1, $this->intentions);
        Http::assertSent(fn ($r) => $r['amount'] === 15000
            && $r['special_reference'] === $payment->public_id
            && $r['payment_methods'] === [(int) self::LIVE_CARD]
            && str_ends_with($r['notification_url'], '/api/payments/paymob/webhook')
            && str_ends_with($r['redirection_url'], '/api/pay/callback')
            && $r->hasHeader('Authorization', 'Token sk_test'));
    }

    // 2
    public function test_same_idempotency_key_returns_the_same_payment_without_a_new_paymob_call(): void
    {
        $this->subscriptionDue();
        $body = ['purpose' => 'subscription', 'reference' => '2026', 'amount' => 100];

        $first = $this->checkout($body, 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa')->assertCreated();
        $second = $this->checkout($body, 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa')->assertOk();

        $this->assertSame($first->json('payment_id'), $second->json('payment_id'));
        $this->assertSame($first->json('checkout_url'), $second->json('checkout_url'));
        $this->assertSame(1, PaymobPayment::count());
        $this->assertSame(1, $this->intentions);
    }

    // 3
    public function test_amount_above_the_remaining_amount_is_rejected(): void
    {
        $this->subscriptionDue(300, paid: 200);

        $this->checkout(['purpose' => 'subscription', 'reference' => '2026', 'amount' => 150])
            ->assertStatus(422)
            ->assertJsonPath('message', 'المبلغ أكبر من المتبقي عليك');

        $this->assertSame(0, PaymobPayment::count());
        $this->assertSame(0, $this->intentions);
    }

    // 4: a donation due may be paid in part; a free donation (no due) takes any amount
    public function test_donation_due_partial_is_allowed_and_free_donation_takes_any_amount(): void
    {
        Due::factory()->forMember($this->member)->amount(500)->create(['item' => 'تبرع المسجد', 'transaction_type' => 'تبرعات']);

        $this->checkout(['purpose' => 'donation', 'reference' => 'تبرع المسجد', 'amount' => 600])
            ->assertStatus(422)->assertJsonPath('message', 'المبلغ أكبر من المتبقي عليك');
        $this->checkout(['purpose' => 'donation', 'reference' => 'تبرع المسجد', 'amount' => 200])->assertCreated();
        $this->checkout(['purpose' => 'donation', 'reference' => 'تبرع عام', 'amount' => 75.5])->assertCreated();

        $this->assertSame(2, PaymobPayment::count());
    }

    // 5
    public function test_sms_amount_must_equal_the_fee_and_sms_must_not_be_active(): void
    {
        $this->checkout(['purpose' => 'sms', 'reference' => '', 'amount' => 50])
            ->assertStatus(422)->assertJsonPath('message', 'مبلغ اشتراك الرسائل غير صحيح');

        $this->checkout(['purpose' => 'sms', 'reference' => '', 'amount' => 60])->assertCreated();

        SMSSubscribers::create([
            'member_id' => 15, 'mobile_no' => 1012345678, 'active_sms' => 1, 'amount' => 60,
            'subscription_start_date' => now()->toDateString(), 'subscription_expiry_date' => now()->addMonths(3)->toDateString(),
        ]);
        $this->checkout(['purpose' => 'sms', 'amount' => 60])
            ->assertStatus(422)->assertJsonPath('message', 'خدمة الرسائل مفعّلة عندك بالفعل');
    }

    // 6
    public function test_another_members_due_cannot_be_checked_out(): void
    {
        $this->subscriptionDue(member: Subscribers::factory()->create(['member_id' => 99]));

        $this->checkout(['purpose' => 'subscription', 'reference' => '2026', 'amount' => 100])
            ->assertStatus(422)->assertJsonPath('message', 'الاشتراك ده مدفوع بالكامل');

        $this->assertSame(0, $this->intentions);
    }

    public function test_wallet_number_is_validated_and_stored_masked(): void
    {
        $this->subscriptionDue();
        $body = ['purpose' => 'subscription', 'reference' => '2026', 'amount' => 100, 'method' => 'wallet'];

        $this->checkout($body + ['wallet_number' => '0123'])
            ->assertStatus(422)->assertJsonPath('message', 'رقم المحفظة غير صحيح');
        $this->checkout($body + ['wallet_number' => '01012345678'])->assertCreated();

        $this->assertSame('010****5678', PaymobPayment::sole()->wallet_number_masked);
        Http::assertSent(fn ($r) => $r['payment_methods'] === [4991600] && $r['billing_data']['phone_number'] === '01012345678');
    }

    public function test_paymob_failure_returns_502_with_an_arabic_message(): void
    {
        $this->paymobDown = true;
        $this->subscriptionDue();

        $this->checkout(['purpose' => 'subscription', 'reference' => '2026', 'amount' => 100])
            ->assertStatus(502)
            ->assertJsonPath('message', 'خدمة الدفع مش متاحة دلوقتي، جرّب تاني بعد شوية');
    }

    // 7
    public function test_a_member_cannot_read_another_members_payment(): void
    {
        $this->subscriptionDue();
        $payment = $this->openCheckout(['purpose' => 'subscription', 'reference' => '2026', 'amount' => 100]);

        $this->getJson("/api/payments/{$payment->public_id}")
            ->assertOk()
            ->assertJson(['status' => 'pending', 'amount' => 100, 'purpose' => 'subscription', 'reference' => '2026'])
            ->assertJsonMissingPath('receipt');

        Sanctum::actingAs(Subscribers::factory()->create(['member_id' => 77]));
        $this->getJson("/api/payments/{$payment->public_id}")->assertNotFound();
    }

    // 8
    public function test_webhook_with_a_bad_hmac_changes_nothing(): void
    {
        $this->subscriptionDue();
        $payment = $this->openCheckout(['purpose' => 'subscription', 'reference' => '2026', 'amount' => 100]);

        $this->webhook($payment, hmac: str_repeat('0', 128))->assertStatus(401);

        $this->assertSame('pending', $payment->fresh()->status->value);
        $this->assertSame(0, PaymentTransaction::count());
    }

    // 9
    public function test_successful_webhook_records_the_payment_like_the_office_once(): void
    {
        Queue::fake();
        $due = $this->subscriptionDue();
        $payment = $this->openCheckout(['purpose' => 'subscription', 'reference' => '2026', 'amount' => 100]);

        $this->webhook($payment)->assertOk();

        $payment->refresh();
        $this->assertSame('paid', $payment->status->value);
        $this->assertSame('700001', $payment->paymob_transaction_id);
        $this->assertEquals(200, $due->fresh()->amount_remaining);

        $transaction = PaymentTransaction::sole();
        $this->assertEquals(100, $transaction->amount);
        $this->assertSame('دفع اونلاين', $transaction->transaction_method);
        $this->assertSame((string) $transaction->id, $payment->receipt_inv_no);
        $this->assertSame((string) $transaction->id, (string) $transaction->inv_no);
        // Online money is held by Paymob, not put in the safe.
        $this->assertSame(0, (int) TotalSafe::first()->amount);

        Queue::assertPushed(CallQueuedListener::class, fn ($job) => $job->class === NotifyMemberOfPayment::class);

        $this->getJson("/api/payments/{$payment->public_id}")
            ->assertJsonPath('status', 'paid')
            ->assertJsonPath('receipt.inv_no', (string) $transaction->id);

        // The history endpoint the app reads shows it.
        $this->getJson('/api/subscriber/subscription-payments-history')->assertJsonFragment(['item' => '2026']);
    }

    // 10
    public function test_the_same_webhook_twice_is_recorded_once(): void
    {
        $due = $this->subscriptionDue();
        $payment = $this->openCheckout(['purpose' => 'subscription', 'reference' => '2026', 'amount' => 100]);

        $this->webhook($payment)->assertOk();
        $this->webhook($payment)->assertOk();

        $this->assertSame(1, PaymentTransaction::count());
        $this->assertEquals(200, $due->fresh()->amount_remaining);
    }

    // 11
    public function test_webhook_with_a_different_amount_fails_and_records_nothing(): void
    {
        $due = $this->subscriptionDue();
        $payment = $this->openCheckout(['purpose' => 'subscription', 'reference' => '2026', 'amount' => 100]);

        $this->webhook($payment, ['amount_cents' => 100])->assertOk();

        $payment->refresh();
        $this->assertSame('failed', $payment->status->value);
        $this->assertSame('amount_mismatch', $payment->failure_reason);
        $this->assertSame(0, PaymentTransaction::count());
        $this->assertEquals(300, $due->fresh()->amount_remaining);
    }

    // 12
    public function test_webhook_from_a_non_live_integration_records_nothing(): void
    {
        $this->subscriptionDue();
        $payment = $this->openCheckout(['purpose' => 'subscription', 'reference' => '2026', 'amount' => 100]);

        $this->webhook($payment, ['integration_id' => 1234567])->assertOk();

        $this->assertSame('pending', $payment->fresh()->status->value);
        $this->assertSame(0, PaymentTransaction::count());
    }

    // 13
    public function test_a_late_webhook_after_expiry_still_records_the_payment(): void
    {
        $due = $this->subscriptionDue();
        $payment = $this->openCheckout(['purpose' => 'subscription', 'reference' => '2026', 'amount' => 100]);

        $this->travel(61)->minutes();
        $this->artisan('payments:expire-pending')->assertSuccessful();
        $this->assertSame('expired', $payment->fresh()->status->value);

        $this->webhook($payment)->assertOk();

        $this->assertSame('paid', $payment->fresh()->status->value);
        $this->assertEquals(200, $due->fresh()->amount_remaining);
    }

    // 14
    public function test_the_redirect_page_records_nothing(): void
    {
        $due = $this->subscriptionDue();
        $payment = $this->openCheckout(['purpose' => 'subscription', 'reference' => '2026', 'amount' => 100]);

        $this->get("/api/pay/callback?success=true&txn_response_code=APPROVED&merchant_order_id={$payment->public_id}&order={$payment->paymob_order_id}")
            ->assertOk()
            ->assertSee('جاري تأكيد الدفع', false);

        $this->assertSame('pending', $payment->fresh()->status->value);
        $this->assertSame(0, PaymentTransaction::count());
        $this->assertEquals(300, $due->fresh()->amount_remaining);
    }

    public function test_declined_webhook_marks_the_payment_failed(): void
    {
        $this->subscriptionDue();
        $payment = $this->openCheckout(['purpose' => 'subscription', 'reference' => '2026', 'amount' => 100]);

        $this->webhook($payment, ['success' => false, 'data' => ['message' => 'Insufficient funds']])->assertOk();

        $this->assertSame('failed', $payment->fresh()->status->value);
        $this->assertSame('Insufficient funds', $payment->fresh()->failure_reason);
        $this->assertSame(0, PaymentTransaction::count());
    }

    public function test_paid_sms_checkout_activates_the_service(): void
    {
        $payment = $this->openCheckout(['purpose' => 'sms', 'reference' => '', 'amount' => 60]);

        $this->webhook($payment)->assertOk();

        $this->assertSame(1, (int) SMSSubscribers::sole()->active_sms);
        $this->assertSame('اشتراك', PaymentTransaction::sole()->transaction_type);
    }

    public function test_paid_free_donation_is_recorded_as_a_donation(): void
    {
        $payment = $this->openCheckout(['purpose' => 'donation', 'reference' => 'تبرع عام', 'amount' => 250]);

        $this->webhook($payment)->assertOk();

        $this->assertSame('تبرعات', PaymentTransaction::sole()->transaction_type);
        $donation = Donations::sole();
        $this->assertSame('تبرع عام', $donation->donation_category);
        $this->assertEquals(250, $donation->amount);
    }
}
