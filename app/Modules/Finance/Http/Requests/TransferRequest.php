<?php

namespace App\Modules\Finance\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class TransferRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1'],
            'proof_img' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            // The two forms name the date field differently.
            'transaction_date' => ['nullable', 'date'],
            'payment_date' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'يجب إدخال المبلغ',
            'amount.min' => 'المبلغ المطلوب للسحب غير متاح، الرجاء كتابة مبلغ متاح',
            'proof_img.required' => 'يجب ارفاق صورة الإثبات',
            'proof_img.image' => 'يجب ان يكون الملف من نوع صورة',
            'proof_img.mimes' => 'يجب ان يكون الملف من نوع :values',
            'proof_img.max' => 'يجب ان لا يتعدى حجم الصورة عن :max',
        ];
    }

    public function transferDate(): CarbonImmutable
    {
        $value = $this->input('transaction_date') ?? $this->input('payment_date');

        return $value ? CarbonImmutable::parse($value) : CarbonImmutable::now();
    }
}
