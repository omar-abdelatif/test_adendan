<?php

namespace App\Modules\Finance\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * POST /api/payments/checkout. The amount is only checked against our records
 * by StartPaymobCheckout; it never decides what is charged on its own.
 */
class PaymobCheckoutRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'purpose' => ['required', 'in:subscription,donation,sms'],
            'reference' => ['required_unless:purpose,sms', 'nullable', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'method' => ['required', 'in:card,wallet'],
            'wallet_number' => ['required_if:method,wallet', 'nullable', 'regex:/^01[0125][0-9]{8}$/'],
            'idempotency_key' => ['required', 'string', 'min:16', 'max:64'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
    }

    public function messages(): array
    {
        return [
            'wallet_number.required_if' => 'رقم المحفظة غير صحيح',
            'wallet_number.regex' => 'رقم المحفظة غير صحيح',
            'amount.*' => 'المبلغ غير صحيح',
            'reference.*' => 'البند المطلوب دفعه غير صحيح',
            'purpose.*' => 'نوع الدفع غير صحيح',
            'method.*' => 'طريقة الدفع غير صحيحة',
            'idempotency_key.*' => 'حصل خطأ في الطلب، جرّب تاني',
        ];
    }

    /** The app shows `message` to the member as is, so it is the first (Arabic) error. */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => $validator->errors()->first(),
            'errors' => $validator->errors(),
        ], 422));
    }
}
