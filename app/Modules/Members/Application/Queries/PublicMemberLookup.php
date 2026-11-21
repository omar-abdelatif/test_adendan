<?php

namespace App\Modules\Members\Application\Queries;

use App\Models\Subscribers;
use App\Modules\Finance\Domain\Enums\DueType;

/**
 * The public "check my dues" page: a visitor types a national ID and sees the
 * member's name, status and dues. Nothing else about the member is returned.
 */
class PublicMemberLookup
{
    private const REQUIRED_FIELDS = [
        'name' => 'الاسم',
        'mobile_no' => 'رقم المحمول',
        'ssn' => 'الرقم القومي',
        'address' => 'العنوان',
        'birthdate' => 'تاريخ الميلاد',
    ];

    private const DUE_COLUMNS = ['id', 'item', 'total_amount', 'amount_paid', 'amount_remaining'];

    /** @return array<string, mixed>|null */
    public function byNationalId(string $ssn): ?array
    {
        $member = Subscribers::where('ssn', $ssn)->first();
        if (! $member) {
            return null;
        }

        $dues = $member->dues()->get([...self::DUE_COLUMNS, 'transaction_type'])->groupBy('transaction_type');
        $only = fn (string $type) => $dues->get($type, collect())->map->only(self::DUE_COLUMNS)->values();

        return [
            'member' => $member->only(['name', 'member_id', 'status']),
            'searched' => true,
            'subsDues' => $only(DueType::Subscription->value),
            'donDues' => $only(DueType::Donation->value),
            'noDelays' => 'لا توجد مستحقات',
            // Which fields staff still need from the member (labels only, no values).
            'missingFields' => array_filter(
                self::REQUIRED_FIELDS,
                fn (string $field) => in_array(trim((string) $member->{$field}), ['', '0'], true),
                ARRAY_FILTER_USE_KEY,
            ),
        ];
    }
}
