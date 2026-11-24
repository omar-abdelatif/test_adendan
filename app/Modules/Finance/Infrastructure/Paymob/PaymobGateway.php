<?php

namespace App\Modules\Finance\Infrastructure\Paymob;

use App\Modules\Finance\Domain\Contracts\PaymentGateway;
use App\Modules\Finance\Domain\Online\CheckoutRequest;
use App\Modules\Finance\Domain\Online\CheckoutSession;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Paymob "Intention" API (unified checkout) and callback signature check.
 */
class PaymobGateway implements PaymentGateway
{
    /**
     * Paymob signs the "processed" callback by concatenating these fields of `obj`
     * in this exact order. Booleans are signed as the words "true"/"false".
     */
    private const HMAC_FIELDS = [
        'amount_cents', 'created_at', 'currency', 'error_occured', 'has_parent_transaction',
        'id', 'integration_id', 'is_3d_secure', 'is_auth', 'is_capture', 'is_refunded',
        'is_standalone_payment', 'is_voided', 'order.id', 'owner', 'pending',
        'source_data.pan', 'source_data.sub_type', 'source_data.type', 'success',
    ];

    /** @param array<string, mixed> $config config('paymob') */
    public function __construct(private readonly array $config) {}

    public function createCheckout(CheckoutRequest $request): CheckoutSession
    {
        $integration = $request->method === 'wallet'
            ? $this->config['wallet_integration_id'] ?? null
            : $this->config['card_integration_id'] ?? null;

        if (! $integration || ! ($this->config['secret_key'] ?? null) || ! ($this->config['public_key'] ?? null)) {
            Log::error('Paymob is not configured', ['method' => $request->method]);
            throw new RuntimeException('Paymob is not configured.');
        }

        [$firstName, $lastName] = $this->splitName($request->customerName);
        $base = (string) ($this->config['base_url'] ?? 'https://accept.paymob.com');

        try {
            $response = Http::withToken((string) $this->config['secret_key'], 'Token')
                ->acceptJson()
                ->timeout(20)
                ->post($base.'/v1/intention/', [
                    'amount' => $request->amount->piasters,
                    'currency' => 'EGP',
                    'payment_methods' => [(int) $integration],
                    'items' => [[
                        'name' => $request->description,
                        'amount' => $request->amount->piasters,
                        'description' => $request->description,
                        'quantity' => 1,
                    ]],
                    'billing_data' => [
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'phone_number' => $request->customerPhone !== '' ? $request->customerPhone : 'NA',
                        'email' => 'NA',
                        'country' => 'EG',
                    ],
                    'special_reference' => $request->reference,
                    'notification_url' => url('/api/payments/paymob/webhook'),
                    'redirection_url' => url('/api/pay/callback'),
                    'extras' => $request->extras,
                ]);
        } catch (ConnectionException $e) {
            Log::error('Paymob intention timed out', ['reference' => $request->reference]);
            throw new RuntimeException('Paymob is unreachable.', previous: $e);
        }

        $clientSecret = $response->json('client_secret');
        if (! $response->successful() || ! $clientSecret) {
            Log::error('Paymob intention failed', ['status' => $response->status(), 'reference' => $request->reference]);
            throw new RuntimeException('Paymob rejected the checkout.');
        }

        $orderId = $response->json('intention_order_id');

        return new CheckoutSession(
            checkoutUrl: $base.'/unifiedcheckout/?'.http_build_query([
                'publicKey' => $this->config['public_key'],
                'clientSecret' => $clientSecret,
            ]),
            providerOrderId: $orderId !== null ? (string) $orderId : null,
        );
    }

    public function verifyCallback(array $transaction, ?string $signature): bool
    {
        $secret = (string) ($this->config['hmac_secret'] ?? '');
        if ($secret === '' || ! $signature) {
            return false;
        }

        $expected = hash_hmac('sha512', $this->signedString($transaction), $secret);
        $valid = hash_equals($expected, strtolower($signature));
        if (! $valid) {
            Log::warning('Paymob HMAC verification failed', ['transaction_id' => $transaction['id'] ?? null]);
        }

        return $valid;
    }

    public function isLiveIntegration(array $transaction): bool
    {
        $live = array_map('strval', (array) ($this->config['live_integration_ids'] ?? []));

        return in_array((string) ($transaction['integration_id'] ?? ''), $live, true);
    }

    /** @param array<string, mixed> $transaction */
    public function signedString(array $transaction): string
    {
        return implode('', array_map(function (string $key) use ($transaction) {
            $value = data_get($transaction, $key, '');
            if (is_bool($value)) {
                return $value ? 'true' : 'false';
            }

            return (string) ($value ?? '');
        }, self::HMAC_FIELDS));
    }

    /** @return array{string, string} */
    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/u', trim($name), 2) ?: [];

        return [($parts[0] ?? '') !== '' ? $parts[0] : 'NA', $parts[1] ?? 'NA'];
    }
}
