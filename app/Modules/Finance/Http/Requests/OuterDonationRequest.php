<?php

namespace App\Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OuterDonationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', 'exists:donators,id'],
            'amount' => ['required', 'numeric', 'min:1'],
            'invoice_id' => ['required', 'numeric', 'unique:outer_donations,invoice_id'],
            'donation_destination' => ['required', 'string', 'max:255'],
            'payment_method' => ['required', 'string', 'max:50'],
            'donator_type' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'amount' => 'المبلغ',
            'invoice_id' => 'رقم الإيصال',
            'donation_destination' => 'جهة التبرع',
            'payment_method' => 'طريقة الدفع',
        ];
    }
}
