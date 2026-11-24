<?php

namespace App\Modules\Cemeteries\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TombRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the route's permission middleware decides
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tomb = $this->route('tomb');

        return [
            'region_id' => ['required', 'integer', 'exists:cemetery_regions,id'],
            'name' => ['required', 'string', 'max:255', Rule::unique('cemetery_tombs', 'name')->ignore($tomb?->id)],
            'kind' => ['required', Rule::in(['eyes', 'lahd'])],
            'serves' => ['required', Rule::in(['men', 'women', 'mixed'])],
            'chambers' => ['required', 'integer', 'min:0', 'max:300'],
            'annual_cost' => ['nullable', 'integer', 'min:0'],
            'location' => ['nullable', 'string', 'max:500'],
            'guard_name' => ['nullable', 'string', 'max:255'],
            'guard_phone' => ['nullable', 'regex:/^0?\d{9,11}$/'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'region_id' => 'المنطقة', 'name' => 'اسم المقبرة', 'kind' => 'النوع', 'serves' => 'لمين', 'chambers' => 'عدد العيون أو اللحود',
            'annual_cost' => 'التكلفة السنوية', 'location' => 'الموقع', 'guard_name' => 'اسم التربي', 'guard_phone' => 'تليفون التربي',
        ];
    }

    /** @return array<string, mixed> */
    public function tombAttributes(): array
    {
        return $this->safe()->except('chambers');
    }
}
