<?php

namespace App\Modules\Members\Application\Queries;

use App\Models\Subscribers;

/**
 * How much of a member's file is filled in, for the members page preview.
 * Only fields the association relies on count; optional ones (work phone,
 * home phone, second mobile) do not.
 */
class ProfileCompleteness
{
    /** Field => how the dashboard names it. */
    public const FIELDS = [
        'img' => 'الصورة الشخصية',
        'id_img' => 'صورة البطاقة',
        'ssn' => 'الرقم القومي',
        'mobile_no' => 'رقم المحمول',
        'birthdate' => 'تاريخ الميلاد',
        'address' => 'العنوان',
        'martial_status' => 'الحالة الإجتماعية',
        'job' => 'الوظيفة',
        'job_destination' => 'جهة العمل',
        'educational_qualification' => 'المؤهل',
    ];

    /** @return list<string> labels of the missing fields, in FIELDS order */
    public function missing(Subscribers $member): array
    {
        $missing = [];
        foreach (self::FIELDS as $field => $label) {
            $value = $member->getAttribute($field);
            // The old import stored 0 for an unknown national ID or mobile.
            if ($value === null || trim((string) $value) === '' || (string) $value === '0') {
                $missing[] = $label;
            }
        }

        return $missing;
    }

    public function percent(Subscribers $member): int
    {
        return (int) round((count(self::FIELDS) - count($this->missing($member))) / count(self::FIELDS) * 100);
    }
}
