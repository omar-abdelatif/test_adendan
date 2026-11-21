<?php

namespace Tests\Feature\Security;

use App\Models\Due;
use App\Models\PaymentTransaction;
use App\Models\Subscribers;
use App\Services\PaymobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymobCallbackTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-hmac-secret';

    protected function setUp(): void
    {
        parent::setUp();

        Subscribers::create([
            'member_id' => 15, 'name' => 'عضو تجريبي', 'slug' => 'member-15',
            'ssn' => 29001011234567, 'birthdate' => '1990-01-01', 'mobile_no' => 1012345678,
        ]);
        Due::create([
            'member_id' => 15, 'item' => '2026', 'transaction_type' => 'إشتراك',
            'total_amount' => 300, 'amount_paid' => 0, 'amount_remaining' => 300,
        ]);
    }

    /** A "processed" callback body as Paymob sends it. */
    private function obj(array $overrides = []): array
    {
        return array_replace_recursive([
            'id' => 987654,
            'amount_cents' => 10000,
            'created_at' => '2026-11-21T10:15:00.000000',
            'currency' => 'EGP',
            'error_occured' => false,
            'has_parent_transaction' => false,
            'integration_id' => 4991596,
            'is_3d_secure' => true,
            'is_auth' => false,
            'is_capture' => false,
            'is_refunded' => false,
            'is_standalone_payment' => true,
            'is_voided' => false,
            'order' => ['id' => 555, 'merchant_order_id' => '15-2026-إشتراك-ref1-01012345678'],
            'owner' => 123,
            'pending' => false,
            'source_data' => ['pan' => '2346', 'sub_type' => 'MasterCard', 'type' => 'card'],
            'success' => true,
        ], $overrides);
    }

    private function signed(array $obj, string $secret = self::SECRET): string
    {
        return hash_hmac('sha512', app(PaymobService::class)->hmacString($obj), $secret);
    }

    private function sendCallback(array $obj, ?string $hmac)
    {
        $url = '/api/paymob/callback' . ($hmac ? '?hmac=' . $hmac : '');
        return $this->postJson($url, ['type' => 'TRANSACTION', 'obj' => $obj]);
    }

    public function test_hmac_string_follows_paymob_field_order_with_boolean_words(): void
    {
        $expected = '10000' . '2026-11-21T10:15:00.000000' . 'EGP' . 'false' . 'false'
            . '987654' . '4991596' . 'true' . 'false' . 'false' . 'false'
            . 'true' . 'false' . '555' . '123' . 'false'
            . '2346' . 'MasterCard' . 'card' . 'true';

        $this->assertSame($expected, app(PaymobService::class)->hmacString($this->obj()));
    }

    public function test_callback_without_hmac_is_rejected_and_records_nothing(): void
    {
        $this->sendCallback($this->obj(), null)->assertStatus(403);

        $this->assertSame(0, PaymentTransaction::count());
        $this->assertEquals(300, Due::first()->amount_remaining);
    }

    public function test_callback_signed_with_the_wrong_secret_is_rejected(): void
    {
        $obj = $this->obj();

        $this->sendCallback($obj, $this->signed($obj, 'attacker-guess'))->assertStatus(403);

        $this->assertSame(0, PaymentTransaction::count());
    }

    public function test_tampered_amount_breaks_the_signature(): void
    {
        $hmac = $this->signed($this->obj());

        $this->sendCallback($this->obj(['amount_cents' => 30000]), $hmac)->assertStatus(403);

        $this->assertEquals(300, Due::first()->amount_remaining);
    }

    public function test_valid_callback_records_payment_and_reduces_the_due(): void
    {
        $obj = $this->obj();

        $this->sendCallback($obj, $this->signed($obj))->assertOk();

        $transaction = PaymentTransaction::sole();
        $this->assertSame('987654', $transaction->paymob_transaction_id);
        $this->assertSame('paid', $transaction->paymob_status);
        $this->assertEquals(100, $transaction->amount);
        $this->assertEquals(200, Due::first()->amount_remaining);
    }

    public function test_repeated_callback_is_applied_only_once(): void
    {
        $obj = $this->obj();
        $hmac = $this->signed($obj);

        $this->sendCallback($obj, $hmac)->assertOk();
        $this->sendCallback($obj, $hmac)->assertOk()->assertJson(['message' => 'تم تسجيل العملية من قبل']);

        $this->assertSame(1, PaymentTransaction::count());
        $this->assertEquals(200, Due::first()->amount_remaining);
    }

    public function test_short_merchant_order_id_is_rejected_instead_of_crashing(): void
    {
        $obj = $this->obj(['order' => ['merchant_order_id' => '15-2026']]);

        $this->sendCallback($obj, $this->signed($obj))->assertStatus(400);

        $this->assertSame(0, PaymentTransaction::count());
    }
}
