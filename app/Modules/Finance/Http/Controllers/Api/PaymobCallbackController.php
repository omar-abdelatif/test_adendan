<?php

namespace App\Modules\Finance\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Application\Actions\HandleOnlinePayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Server-to-server notification from Paymob after a payment. */
class PaymobCallbackController extends Controller
{
    public function __invoke(Request $request, HandleOnlinePayment $handle): JsonResponse
    {
        $transaction = $request->input('obj');

        if ($request->input('type') !== 'TRANSACTION' || ! is_array($transaction)) {
            return response()->json(['message' => 'بيانات غير صحيحة'], 400);
        }

        $result = $handle->handle($transaction, $request->query('hmac'));

        return response()->json(['message' => $result->message()], $result->httpStatus());
    }
}
