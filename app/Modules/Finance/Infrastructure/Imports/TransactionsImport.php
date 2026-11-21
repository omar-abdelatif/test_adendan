<?php

namespace App\Modules\Finance\Infrastructure\Imports;

use App\Models\PaymentTransaction;
use App\Modules\Finance\Domain\Enums\Channel;
use App\Modules\Shared\Infrastructure\Excel\ExcelValues as Value;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Historical payments from a spreadsheet. These are past records, not new cash,
 * so they are written as they are and do not change the safe or bank balance.
 *
 * Columns: member_id, amount, payment_date, payment_method, payment_cat,
 * transaction_type, transaction_cat, item, inv_no.
 */
class TransactionsImport implements ToCollection, WithCalculatedFormulas, WithHeadingRow
{
    public int $imported = 0;

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $amount = Value::integer($row['amount'] ?? null);
            $type = Value::text($row['transaction_type'] ?? null);
            if ($amount === null || $type === null) {
                continue;
            }

            // Excel turns a year typed in the item column into a date serial.
            $item = $row['item'] ?? null;
            if (is_numeric($item) && (int) $item > 3000) {
                $item = Value::date($item);
            }

            PaymentTransaction::create([
                'member_id' => Value::integer($row['member_id'] ?? null),
                'amount' => $amount,
                'payment_date' => Value::date($row['payment_date'] ?? null),
                'payment_method' => Value::text($row['payment_method'] ?? null) ?? 'كاش',
                'payment_cat' => Value::text($row['payment_cat'] ?? null) ?? 'كلي',
                'transaction_type' => $type,
                'transaction_cat' => Value::text($row['transaction_cat'] ?? null) ?? 'ايداع',
                'transaction_method' => Channel::Office->value,
                'item' => Value::text($item) ?? '-',
                'inv_no' => Value::integer($row['inv_no'] ?? null),
            ]);
            $this->imported++;
        }
    }
}
