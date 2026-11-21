<?php

namespace App\Modules\Messaging\Infrastructure\Imports;

use App\Models\SMSSubscribers;
use App\Modules\Shared\Infrastructure\Excel\ExcelValues as Value;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Existing SMS subscribers from a spreadsheet. Columns: member_id (empty for
 * non-members), mobile_no, amount, subscription_start_date,
 * subscription_expiry_date, active_sms. Rows without a mobile number are skipped.
 */
class SmsSubscribersImport implements ToCollection, WithHeadingRow
{
    public int $imported = 0;

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $mobile = Value::integer(preg_replace('/\D/', '', (string) ($row['mobile_no'] ?? '')));
            if (! $mobile) {
                continue;
            }

            SMSSubscribers::create([
                'member_id' => Value::integer($row['member_id'] ?? null),
                'mobile_no' => $mobile,
                'amount' => Value::integer($row['amount'] ?? null) ?? 0,
                'subscription_start_date' => Value::date($row['subscription_start_date'] ?? null),
                'subscription_expiry_date' => Value::date($row['subscription_expiry_date'] ?? null),
                'active_sms' => (int) (bool) ($row['active_sms'] ?? 1),
            ]);
            $this->imported++;
        }
    }
}
