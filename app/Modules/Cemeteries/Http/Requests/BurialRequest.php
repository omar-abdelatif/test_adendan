<?php

namespace App\Modules\Cemeteries\Http\Requests;

use App\Models\Subscribers;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/** A burial in a chamber or in the village log, or (with `past`) one from the old records. */
class BurialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the route's permission middleware decides
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $past = $this->boolean('past');

        return [
            'region_id' => ['required', 'integer', 'exists:cemetery_regions,id'],
            'tomb_id' => ['nullable', 'integer', 'exists:cemetery_tombs,id'],
            'chamber_id' => ['nullable', 'integer', 'exists:cemetery_chambers,id'],
            'member_id' => ['nullable', 'integer', 'exists:subscribers,member_id'],
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'gender' => [$past ? 'nullable' : 'required', Rule::in(['male', 'female'])],
            'age' => ['nullable', 'integer', 'min:0', 'max:130'],
            'places' => ['nullable', 'integer', 'min:1', 'max:6'],
            'death_place' => ['nullable', 'string', 'max:255'],
            'death_date' => ['nullable', 'date', 'before_or_equal:today'],
            'burial_date' => [$past ? 'nullable' : 'required', 'date', 'before_or_equal:today', 'after_or_equal:death_date'],
            'burial_cost' => ['nullable', 'integer', 'min:0'],
            'washer' => ['nullable', 'string', 'max:255'],
            'carrier' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'image', 'max:4096'],
            'certificate' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'region_id' => 'المنطقة', 'tomb_id' => 'المقبرة', 'chamber_id' => 'العين', 'member_id' => 'رقم العضوية', 'name' => 'الاسم',
            'gender' => 'النوع', 'age' => 'السن', 'places' => 'عدد الأماكن', 'death_place' => 'مكان الوفاة', 'death_date' => 'تاريخ الوفاة',
            'burial_date' => 'تاريخ الدفن', 'burial_cost' => 'تكلفة الدفن', 'washer' => 'المغسل', 'carrier' => 'الحامل', 'notes' => 'الملاحظات',
            'photo' => 'الصورة', 'certificate' => 'شهادة الوفاة',
        ];
    }

    /** The validated fields, with the membership number turned into the member's id. @return array<string, mixed> */
    public function burialData(): array
    {
        $data = $this->safe()->except(['member_id', 'photo', 'certificate', 'past']);
        $data['subscriber_id'] = $this->filled('member_id') ? Subscribers::where('member_id', $this->integer('member_id'))->value('id') : null;

        return $data;
    }

    /** @return array<string, UploadedFile|null> */
    public function uploads(): array
    {
        return ['photo' => $this->file('photo'), 'certificate' => $this->file('certificate')];
    }
}
