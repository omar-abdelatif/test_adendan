<?php

namespace App\Modules\Directory\Infrastructure;

use App\Models\Wedding;
use App\Modules\Shared\Infrastructure\Excel\ExcelValues as Value;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Weddings from a spreadsheet. Columns: day, date, groom_name,
 * pride_father_name, address, from_time, to_time. Rows without a date are skipped.
 */
class WeddingsImport implements ToCollection, WithHeadingRow
{
    public int $imported = 0;

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $date = Value::date($row['date'] ?? null);
            if ($date === null) {
                continue;
            }

            Wedding::create([
                'day' => Value::text($row['day'] ?? null) ?? '',
                'date' => $date,
                'groom_name' => Value::text($row['groom_name'] ?? null) ?? '',
                'pride_father_name' => Value::text($row['pride_father_name'] ?? null) ?? '',
                'address' => Value::text($row['address'] ?? null) ?? '',
                'from_time' => Value::time($row['from_time'] ?? null) ?? '',
                'to_time' => Value::time($row['to_time'] ?? null) ?? '',
            ]);
            $this->imported++;
        }
    }
}
