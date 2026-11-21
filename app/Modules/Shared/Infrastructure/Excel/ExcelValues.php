<?php

namespace App\Modules\Shared\Infrastructure\Excel;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Throwable;

/**
 * Turns Excel cell values into what the database expects. Excel stores dates
 * as serial numbers, and staff also type them as text in several formats.
 * One copy of this logic instead of one per import class.
 */
final class ExcelValues
{
    public static function date(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if (is_numeric($value)) {
                return Carbon::instance(Date::excelToDateTimeObject((float) $value))->format('Y-m-d');
            }
            if ($value instanceof \DateTimeInterface) {
                return Carbon::instance($value)->format('Y-m-d');
            }
            if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', (string) $value)) {
                return Carbon::createFromFormat('d/m/Y', (string) $value)->format('Y-m-d');
            }

            return Carbon::parse((string) $value)->format('Y-m-d');
        } catch (Throwable) {
            logger()->warning('Excel date could not be read', ['value' => $value]);

            return null;
        }
    }

    public static function time(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return is_numeric($value)
                ? Carbon::instance(Date::excelToDateTimeObject((float) $value))->format('g:i A')
                : Carbon::parse((string) $value)->format('g:i A');
        } catch (Throwable) {
            return null;
        }
    }

    public static function text(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }

    public static function integer(mixed $value): ?int
    {
        $text = trim((string) ($value ?? ''));

        return is_numeric($text) ? (int) $text : null;
    }
}
