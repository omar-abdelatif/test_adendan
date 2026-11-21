<?php

namespace App\Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CostYearRequest extends FormRequest
{
    public function rules(): array
    {
        $updating = $this->routeIs('costyears.update');

        return [
            'id' => [$updating ? 'required' : 'prohibited', 'integer', 'exists:cost_years,id'],
            'year' => ['required', 'integer', 'digits:4', Rule::unique('cost_years', 'year')->ignore($updating ? $this->input('id') : null)],
            'cost' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function attributes(): array
    {
        return ['year' => 'السنة', 'cost' => 'قيمة الاشتراك'];
    }
}
