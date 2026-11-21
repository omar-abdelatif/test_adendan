<?php

namespace App\Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PayDueRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'id' => ['required', 'integer'],
            'member_id' => ['required', 'integer'],
            'item' => ['required', 'string', 'max:255'],
            'amount_paid' => ['required', 'numeric', 'min:1'],
            'inv_no' => ['required', 'numeric', 'unique:payment_transactions,inv_no'],
            'payment_method' => ['required', 'string', 'max:50'],
            'payment_date' => ['nullable', 'date'],
        ];
    }

    public function attributes(): array
    {
        return [
            'amount_paid' => 'المبلغ المدفوع',
            'inv_no' => 'رقم الإيصال',
            'payment_method' => 'طريقة الدفع',
            'payment_date' => 'تاريخ الدفع',
        ];
    }
}
