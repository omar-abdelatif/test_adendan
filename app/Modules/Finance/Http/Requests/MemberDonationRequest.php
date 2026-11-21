<?php

namespace App\Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MemberDonationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'member_id' => ['required', 'integer', 'exists:subscribers,member_id'],
            'amount' => ['required', 'numeric', 'min:1'],
            'invoice_no' => ['nullable', 'numeric'],
            'donation_type' => ['required', 'string', 'max:255'],
            'donation_category' => ['nullable', 'string', 'max:255'],
            'other_donation' => ['nullable', 'string', 'max:255'],
            'payment_date' => ['nullable', 'date'],
        ];
    }

    public function attributes(): array
    {
        return [
            'member_id' => 'رقم العضوية',
            'amount' => 'المبلغ',
            'invoice_no' => 'رقم الإيصال',
            'donation_type' => 'نوع التبرع',
        ];
    }
}
