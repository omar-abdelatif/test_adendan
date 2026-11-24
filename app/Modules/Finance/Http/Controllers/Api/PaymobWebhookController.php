<?php

namespace App\Modules\Finance\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Application\Actions\HandleOnlinePayment;
use App\Modules\Finance\Application\Actions\RecordPaymobPayment;
use App\Modules\Finance\Domain\Contracts\PaymentGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Paymob's "transaction processed" callback (server to server). The only way a
 * payment becomes paid. Order: signature, then live integration, then record.
 */
class PaymobWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        PaymentGateway $gateway,
        RecordPaymobPayment $record,
        HandleOnlinePayment $legacy,
    ): JsonResponse {
        $transaction = $request->input('obj');
        if (! is_array($transaction)) {
            return response()->json(['message' => 'بيانات غير صحيحة'], 400);
        }

        // 1. The signature, before anything else is read or written.
        if (! $gateway->verifyCallback($transaction, $request->query('hmac'))) {
            return response()->json(['message' => 'بيانات غير صحيحة (فشل التشفير)'], 401);
        }

        // 2. Sandbox (test) integrations never record anything.
        if (! $gateway->isLiveIntegration($transaction)) {
            Log::info('Paymob callback from a non-live integration ignored', ['integration_id' => $transaction['integration_id'] ?? null]);

            return response()->json(['message' => 'تم الاستلام']);
        }

        if ($request->input('type', 'TRANSACTION') !== 'TRANSACTION') {
            return response()->json(['message' => 'تم الاستلام']);
        }

        // 3. A checkout opened by the server.
        if ($record->handle($transaction)) {
            return response()->json(['message' => 'تم الاستلام']);
        }

        // Older app versions build their own Paymob order ("memberId-item-..."): keep recording those.
        if (preg_match('/^\d+-/', (string) data_get($transaction, 'order.merchant_order_id', ''))) {
            $result = $legacy->handle($transaction, $request->query('hmac'));

            // Always 200: the signature is valid, so a retry from Paymob would not change the outcome.
            return response()->json(['message' => $result->message()]);
        }

        Log::warning('Paymob callback for an unknown order', [
            'transaction_id' => $transaction['id'] ?? null,
            'order_id' => data_get($transaction, 'order.id'),
        ]);

        return response()->json(['message' => 'تم الاستلام']);
    }
}
