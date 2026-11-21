<?php

namespace App\Imports;

use App\Models\Wedding;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class WeddingImport implements ToCollection, WithBatchInserts, WithHeadingRow
{
    public function collection(Collection $collection)
    {
        foreach ($collection as $row) {
            $weddingDate = $this->parseExcelDate($row['date']);
            Wedding::create([
                'day' => $row['day'],
                'date' => $weddingDate ?? null,
                'groom_name' => $row['groom_name'] ?? null,
                'pride_father_name' => $row['pride_father_name'] ?? null,
                'address' => $row['address'],
                'from_time' => $this->parseExcelTime($row['from_time'] ?? null),
                'to_time' => $this->parseExcelTime($row['to_time'] ?? null),
            ]);
        }
    }

    private function parseExcelDate($value)
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            if (is_numeric($value)) {
                return Carbon::instance(Date::excelToDateTimeObject($value))->format('Y-m-d');
            }
            if ($value instanceof \DateTime) {
                return Carbon::instance($value)->format('Y-m-d');
            }
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value)) {
                return Carbon::createFromFormat('d/m/Y', $value)->format('Y-m-d');
            }

            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $e) {
            logger()->error('Date Parse Error', [
                'value' => $value,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function parseExcelTime($value)
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            if (is_numeric($value)) {
                return Carbon::instance(
                    Date::excelToDateTimeObject($value)
                )->format('g:i A');
            }

            return Carbon::parse($value)->format('g:i A');
        } catch (\Exception $e) {
            return null;
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
