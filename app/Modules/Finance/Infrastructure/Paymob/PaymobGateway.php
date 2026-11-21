<?php

namespace App\Modules\Finance\Infrastructure\Paymob;

use App\Modules\Finance\Domain\Contracts\PaymentGateway;
use App\Modules\Finance\Domain\Online\CheckoutRequest;
use App\Modules\Finance\Domain\Online\CheckoutSession;
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

    /** @param array<string, mixed> $config config('services.paymob') */
    public function __construct(private readonly array $config) {}

    public function createCheckout(CheckoutRequest $request): CheckoutSession
    {
        $integration = $request->method === 'wallet'
            ? $this->config['wallet_integration_id']
            : $this->config['card_integration_id'];

        [$firstName, $lastName] = $this->splitName($request->customerName);

        $response = Http::withToken((string) $this->config['secret'], 'Token')
            ->acceptJson()
            ->timeout(20)
            ->post((string) $this->config['intention_url'], [
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
                    'phone_number' => $request->customerPhone,
                    'email' => 'NA',
                    'country' => 'EG',
                ],
                'special_reference' => (string) $request->reference,
                'notification_url' => url('/api/paymob/callback'),
            ]);

        if (! $response->successful() || ! $response->json('client_secret')) {
            Log::error('Paymob intention failed', ['status' => $response->status()]);
            throw new RuntimeException('تعذر بدء عملية الدفع، برجاء المحاولة لاحقًا.');
        }

        $secret = (string) $response->json('client_secret');

        return new CheckoutSession(
            checkoutUrl: 'https://accept.paymob.com/unifiedcheckout/?'.http_build_query([
                'publicKey' => $this->config['public'],
                'clientSecret' => $secret,
            ]),
            clientSecret: $secret,
            reference: (string) $request->reference,
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

        return [$parts[0] ?? 'NA', $parts[1] ?? 'NA'];
    }
}
