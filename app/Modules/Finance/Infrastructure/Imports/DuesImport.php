<?php

namespace App\Modules\Finance\Infrastructure\Imports;

use App\Models\Due;
use App\Modules\Shared\Infrastructure\Excel\ExcelValues as Value;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Members' existing dues from a spreadsheet.
 * Columns: member_id, item, total_amount, amount_paid, amount_remaining, transaction_type.
 * Rows without a member id or item are skipped.
 */
class DuesImport implements ToCollection, WithCalculatedFormulas, WithHeadingRow
{
    public int $imported = 0;

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $memberId = Value::integer($row['member_id'] ?? null);
            $item = Value::text($row['item'] ?? null);
            if ($memberId === null || $item === null) {
                continue;
            }

            $total = Value::integer($row['total_amount'] ?? null) ?? 0;
            $paid = Value::integer($row['amount_paid'] ?? null) ?? 0;

            Due::create([
                'member_id' => $memberId,
                'item' => $item,
                'total_amount' => $total,
                'amount_paid' => $paid,
                'amount_remaining' => Value::integer($row['amount_remaining'] ?? null) ?? max(0, $total - $paid),
                'transaction_type' => Value::text($row['transaction_type'] ?? null) ?? 'إشتراك',
            ]);
            $this->imported++;
        }
    }
}
