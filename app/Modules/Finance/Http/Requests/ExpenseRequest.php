<?php

namespace App\Modules\Finance\Http\Requests;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Http\FormRequest;

class ExpenseRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'id' => [$this->routeIs('miscellaneous.update') ? 'required' : 'prohibited', 'integer', 'exists:miscellaneouses,id'],
            'category' => ['required', 'string', 'max:255'],
            'other_category' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:1'],
            'invoice_img' => Controller::IMAGE_RULE,
        ];
    }

    public function attributes(): array
    {
        return [
            'category' => 'البند',
            'other_category' => 'البند الآخر',
            'amount' => 'المبلغ',
            'invoice_img' => 'صورة الفاتورة',
        ];
    }
}
