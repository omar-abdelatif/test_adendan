<?php

namespace App\Imports;

use App\Models\SMSSubscribers;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class importSmsSybscribers implements ToCollection, WithBatchInserts, WithHeadingRow
{
    public function collection(Collection $collection)
    {
        foreach ($collection as $row) {
            SMSSubscribers::create([
                'member_id' => $row['member_id'],
                'mobile_no' => $row['mobile_no'],
                'amount' => $row['amount'],
                'subscription_expiry_date' => $row['subscription_expiry_date'],
                'acive_sms' => $row['acive_sms'],
            ]);
        }
    }

    public function headingRow(): int
    {
        return 1;
    }

    public function batchSize(): int
    {
        return 100;
    }
}
