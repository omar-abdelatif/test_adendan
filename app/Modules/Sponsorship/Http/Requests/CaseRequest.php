<?php

namespace App\Modules\Sponsorship\Http\Requests;

use App\Models\SponsorshipCase;
use App\Modules\Sponsorship\Domain\Enums\BenefitPeriod;
use App\Modules\Sponsorship\Domain\Enums\BenefitType;
use App\Modules\Sponsorship\Domain\Enums\CaseDocument;
use App\Modules\Sponsorship\Domain\Enums\MaritalStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/** Adding or changing a case, with its papers (ID card, research, photos). */
class CaseRequest extends FormRequest
{
    private const FILE = ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:4096'];

    protected function prepareForValidation(): void
    {
        $digits = fn (string $key) => $this->filled($key) ? preg_replace('/\D/', '', (string) $this->input($key)) : null;
        $this->merge(['national_id' => $digits('national_id'), 'phone' => $this->phone($digits('phone')), 'phone_alt' => $this->phone($digits('phone_alt'))]);
    }

    public function rules(): array
    {
        $case = $this->route('case');

        return [
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'national_id' => ['nullable', 'digits:14', Rule::unique('sponsorship_cases', 'national_id')->ignore($case instanceof SponsorshipCase ? $case->id : null)],
            'phone' => ['nullable', 'regex:/^0\d{9,10}$/'],
            'phone_alt' => ['nullable', 'regex:/^0\d{9,10}$/'],
            'age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'governorate' => ['nullable', 'string', 'max:60'],
            'marital_status' => ['nullable', Rule::enum(MaritalStatus::class)],
            'health_status' => ['nullable', 'string', 'max:255'],
            'sons' => ['nullable', 'integer', 'min:0', 'max:30'],
            'daughters' => ['nullable', 'integer', 'min:0', 'max:30'],
            'monthly_income' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'pension_income' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'other_income' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'benefit_type' => ['required', Rule::enum(BenefitType::class)],
            'benefit_period' => ['required', Rule::enum(BenefitPeriod::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'id_card' => ['nullable', ...self::FILE],
            'research' => ['nullable', ...self::FILE],
            'photos' => ['nullable', 'array', 'max:10'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'الاسم', 'national_id' => 'الرقم القومي', 'phone' => 'رقم الموبايل', 'phone_alt' => 'الموبايل التاني', 'age' => 'السن',
            'address' => 'العنوان', 'governorate' => 'المحافظة', 'marital_status' => 'الحالة الاجتماعية', 'health_status' => 'الحالة الصحية',
            'sons' => 'عدد الولاد', 'daughters' => 'عدد البنات', 'monthly_income' => 'الدخل الشهري', 'pension_income' => 'المعاش',
            'other_income' => 'دخل تاني', 'benefit_type' => 'نوع الاستفادة', 'benefit_period' => 'مدة الاستفادة', 'notes' => 'ملاحظات',
            'id_card' => 'صورة البطاقة', 'research' => 'بحث الحالة', 'photos' => 'الصور', 'photos.*' => 'الصورة',
        ];
    }

    public function messages(): array
    {
        return ['phone.regex' => 'رقم الموبايل لازم يكون 11 رقم ويبدأ بـ 0.', 'phone_alt.regex' => 'رقم الموبايل التاني لازم يكون 11 رقم ويبدأ بـ 0.'];
    }

    /** @return array<string, mixed> */
    public function caseData(): array
    {
        $data = $this->safe()->except(['id_card', 'research', 'photos']);
        foreach (['sons', 'daughters', 'monthly_income', 'pension_income', 'other_income'] as $number) {
            $data[$number] = (int) ($data[$number] ?? 0);
        }

        return $data;
    }

    /** @return list<array{0: CaseDocument, 1: UploadedFile}> */
    public function uploads(): array
    {
        $uploads = [];
        foreach (['id_card' => CaseDocument::IdCard, 'research' => CaseDocument::Research] as $field => $kind) {
            if ($this->file($field) instanceof UploadedFile) {
                $uploads[] = [$kind, $this->file($field)];
            }
        }
        foreach ((array) $this->file('photos', []) as $photo) {
            if ($photo instanceof UploadedFile) {
                $uploads[] = [CaseDocument::Photo, $photo];
            }
        }

        return $uploads;
    }

    /** Numbers typed without the leading zero (1012345678) get it back. */
    private function phone(?string $digits): ?string
    {
        if (! $digits) {
            return null;
        }

        return strlen($digits) === 10 && str_starts_with($digits, '1') ? '0'.$digits : $digits;
    }
}
