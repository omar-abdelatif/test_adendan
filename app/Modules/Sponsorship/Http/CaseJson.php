<?php

namespace App\Modules\Sponsorship\Http;

use App\Models\SponsorshipCase;

/** What the case form needs to open on an existing case (the "تعديل" buttons carry it). */
final class CaseJson
{
    /** @return array<string, mixed> */
    public static function of(SponsorshipCase $case): array
    {
        return [
            'url' => route('sponsorship.cases.update', $case),
            'name' => $case->name,
            'national_id' => $case->national_id,
            'phone' => $case->phone,
            'phone_alt' => $case->phone_alt,
            'age' => $case->age,
            'address' => $case->address,
            'governorate' => $case->governorate,
            'marital_status' => $case->marital_status?->value,
            'health_status' => $case->health_status,
            'sons' => $case->sons,
            'daughters' => $case->daughters,
            'monthly_income' => $case->monthly_income,
            'pension_income' => $case->pension_income,
            'other_income' => $case->other_income,
            'benefit_type' => $case->benefit_type->value,
            'benefit_period' => $case->benefit_period->value,
            'notes' => $case->notes,
        ];
    }
}
