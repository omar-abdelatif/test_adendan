<?php

namespace App\Modules\Members\Http\Requests;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The new-member form and the edit-member form share these rules. */
class MemberRequest extends FormRequest
{
    /** Columns staff may set from the forms (img/id_img/status are handled separately). */
    public const FIELDS = [
        'member_id', 'name', 'nickname', 'ssn', 'address', 'birthdate', 'mobile_no', 'another_mobile_no',
        'job', 'job_tel', 'home_tel', 'job_address', 'job_destination', 'martial_status', 'membership_type',
        'educational_qualification', 'qualification_date',
    ];

    public function rules(): array
    {
        $id = $this->routeIs('subscribe.update') ? $this->input('id') : null;
        $unique = fn (string $column) => Rule::unique('subscribers', $column)->ignore($id);

        return [
            'id' => [$id ? 'required' : 'prohibited', 'integer', 'exists:subscribers,id'],
            'member_id' => ['required', 'integer', 'min:1', $unique('member_id')],
            'name' => ['required', 'string', 'max:255'],
            'nickname' => ['nullable', 'string', 'max:255'],
            'ssn' => ['required', 'digits:14', $unique('ssn')],
            'mobile_no' => ['required', 'regex:/^01[0-9]{9}$/', $unique('mobile_no')],
            'another_mobile_no' => ['nullable', 'regex:/^01[0-9]{9}$/'],
            'address' => ['nullable', 'string', 'max:255'],
            'birthdate' => ['nullable', 'date'],
            'job' => ['nullable', 'string', 'max:255'],
            'job_tel' => ['nullable', 'string', 'max:30'],
            'home_tel' => ['nullable', 'string', 'max:30'],
            'job_address' => ['nullable', 'string', 'max:255'],
            'job_destination' => ['nullable', 'string', 'max:255'],
            'martial_status' => ['nullable', 'string', 'max:50'],
            'membership_type' => ['nullable', 'string', 'max:50'],
            'educational_qualification' => ['nullable', 'string', 'max:255'],
            'qualification_date' => ['nullable', 'string', 'max:50'],
            'tomb_name' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable'],
            'inv_no' => ['nullable', 'numeric'],
            'img' => Controller::IMAGE_RULE,
            'id_img' => Controller::IMAGE_RULE,
        ];
    }

    public function attributes(): array
    {
        return [
            'member_id' => 'رقم العضوية',
            'name' => 'الاسم',
            'ssn' => 'الرقم القومي',
            'mobile_no' => 'رقم الهاتف',
            'another_mobile_no' => 'رقم الهاتف الآخر',
            'birthdate' => 'تاريخ الميلاد',
            'img' => 'الصورة الشخصية',
            'id_img' => 'صورة البطاقة',
            'inv_no' => 'رقم الإيصال',
        ];
    }

    /** @return array<string, mixed> */
    public function memberAttributes(): array
    {
        $fields = $this->routeIs('subscribe.update') ? [...self::FIELDS, 'tomb_name'] : self::FIELDS;

        return $this->only($fields);
    }
}
