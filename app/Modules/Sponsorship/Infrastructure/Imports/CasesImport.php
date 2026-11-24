<?php

namespace App\Modules\Sponsorship\Infrastructure\Imports;

use App\Models\SponsorshipCase;
use App\Modules\Shared\Infrastructure\Excel\ExcelValues as Value;
use App\Modules\Sponsorship\Application\Actions\ManageCases;
use App\Modules\Sponsorship\Domain\Enums\BenefitPeriod;
use App\Modules\Sponsorship\Domain\Enums\BenefitType;
use App\Modules\Sponsorship\Domain\Enums\MaritalStatus;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Cases from a spreadsheet. Reads the new headings (name, national_id, phone…)
 * and the old system's (fullname, ssn, phone_number, gov, retire_income,
 * another_source, benefit_duration), so its exported sheets load as they are.
 * A row is skipped when its national ID is already in, or when its benefit
 * type or period cannot be read.
 */
class CasesImport implements ToCollection, WithHeadingRow
{
    public int $imported = 0;

    /** @var list<string> */
    public array $skipped = [];

    public function __construct(private readonly ManageCases $cases) {}

    public function collection(Collection $rows): void
    {
        foreach ($rows as $i => $row) {
            $line = $i + 2; // row 1 holds the headings
            $get = fn (string ...$keys) => collect($keys)->map(fn ($k) => $row[$k] ?? null)->first(fn ($v) => $v !== null && $v !== '');
            $name = Value::text($get('name', 'fullname'));
            if ($name === null) {
                continue;
            }

            $type = BenefitType::fromText(Value::text($get('benefit_type')));
            $period = BenefitPeriod::fromText(Value::text($get('benefit_period', 'benefit_duration')));
            if (! $type || ! $period) {
                $this->skipped[] = "سطر {$line}: {$name} من غير نوع أو مدة استفادة";

                continue;
            }

            $nationalId = $this->digits($get('national_id', 'ssn'));
            $phone = $this->phone($get('phone', 'phone_number'));
            $exists = $nationalId
                ? SponsorshipCase::query()->where('national_id', $nationalId)->exists()
                : SponsorshipCase::query()->where('name', $name)->where('phone', $phone)->exists();
            if ($exists) {
                $this->skipped[] = "سطر {$line}: {$name} متسجلة قبل كده";

                continue;
            }

            $this->cases->create([
                'name' => $name,
                'national_id' => $nationalId && strlen($nationalId) === 14 ? $nationalId : null,
                'phone' => $phone,
                'phone_alt' => $this->phone($get('phone_alt', 'another_phone_number')),
                'age' => Value::integer($get('age')),
                'address' => Value::text($get('address')),
                'governorate' => Value::text($get('governorate', 'gov')),
                'marital_status' => MaritalStatus::fromText(Value::text($get('marital_status'))),
                'health_status' => Value::text($get('health_status')),
                'sons' => max(0, (int) Value::integer($get('sons'))),
                'daughters' => max(0, (int) Value::integer($get('daughters'))),
                'monthly_income' => max(0, (int) Value::integer($get('monthly_income'))),
                'pension_income' => max(0, (int) Value::integer($get('pension_income', 'retire_income'))),
                'other_income' => max(0, (int) Value::integer($get('other_income', 'another_source'))),
                'benefit_type' => $type,
                'benefit_period' => $period,
            ]);
            $this->imported++;
        }
    }

    /** Excel turns long numbers into floats (2.9E+13); read them back as digits. */
    private function digits(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $text = is_float($value) || (is_string($value) && stripos($value, 'e+') !== false) ? number_format((float) $value, 0, '', '') : (string) $value;
        $digits = preg_replace('/\D/', '', $text);

        return $digits === '' ? null : $digits;
    }

    /** Mobile numbers lost their leading zero in the old database; put it back. */
    private function phone(mixed $value): ?string
    {
        $digits = $this->digits($value);
        if ($digits === null) {
            return null;
        }

        return strlen($digits) === 10 && str_starts_with($digits, '1') ? '0'.$digits : $digits;
    }
}
