<?php

namespace App\Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Mobile app: start an online payment. Only the due id and method come from the app. */
class CheckoutRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'due_id' => [$this->routeIs('api.payments.checkout') ? 'required' : 'prohibited', 'integer'],
            'payment_method' => ['required', 'in:card,wallet'],
        ];
    }
}
