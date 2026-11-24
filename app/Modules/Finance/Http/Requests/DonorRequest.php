<?php

namespace App\Modules\Finance\Http\Requests;

use App\Models\Donators;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A donor: one row per person in the shared donors table, whatever fund they give to.
 * The mobile number is the person's key; it is stored as a number, so 010… and 10… are the same.
 */
class DonorRequest extends FormRequest
{
    public const TYPES = ['منتظم', 'موسمي'];

    protected function prepareForValidation(): void
    {
        if ($this->filled('mobile_number')) {
            $this->merge(['mobile_number' => preg_replace('/\D/', '', (string) $this->input('mobile_number'))]);
        }
    }

    public function rules(): array
    {
        $donor = $this->route('donor') ?? ($this->filled('id') ? Donators::find($this->integer('id')) : null);

        return [
            'name' => ['required', 'string', 'max:255'],
            'mobile_number' => ['required', 'regex:/^0?1[0-9]{9}$/', Rule::unique('donators', 'mobile_number')->ignore($donor instanceof Donators ? $donor->id : null)],
            'donator_type' => ['required', 'string', 'max:255'],
            'duration' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'الاسم', 'mobile_number' => 'رقم الهاتف', 'donator_type' => 'نوع المتبرع', 'duration' => 'المدة'];
    }

    public function messages(): array
    {
        return ['mobile_number.unique' => 'الرقم ده متسجل لمتبرع تاني. دوّر عليه في القايمة.'];
    }

    /** @return array{name: string, mobile_number: string, donator_type: string, duration: ?string} */
    public function donorData(): array
    {
        /** @var array{name: string, mobile_number: string, donator_type: string, duration: ?string} */
        return $this->safe()->only(['name', 'mobile_number', 'donator_type', 'duration']) + ['duration' => null];
    }
}
