<?php

namespace App\Modules\Members\Infrastructure\Imports;

use App\Models\Subscribers;
use App\Modules\Shared\Infrastructure\Excel\ExcelValues as Value;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Creates or updates members from a spreadsheet, matched on member_id.
 * Rows without a member id are skipped.
 */
class MembersImport implements ToCollection, WithHeadingRow
{
    public int $imported = 0;

    private const TEXT_COLUMNS = [
        'nickname', 'address', 'educational_qualification', 'qualification_date', 'job',
        'job_destination', 'job_tel', 'job_address', 'home_tel', 'martial_status', 'membership_type',
    ];

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $memberId = Value::integer($row['member_id'] ?? null);
            if ($memberId === null) {
                continue;
            }

            $name = Value::text($row['name'] ?? null);
            $attributes = [
                'name' => $name,
                'slug' => $name ? Str::slug($name).'-'.$memberId : null,
                'ssn' => Value::integer($row['ssn'] ?? null),
                'mobile_no' => Value::integer($row['mobile_no'] ?? null),
                'birthdate' => Value::date($row['birthdate'] ?? null),
            ];
            foreach (self::TEXT_COLUMNS as $column) {
                $attributes[$column] = Value::text($row[$column] ?? null);
            }

            Subscribers::updateOrCreate(['member_id' => $memberId], $attributes);
            $this->imported++;
        }
    }
}
