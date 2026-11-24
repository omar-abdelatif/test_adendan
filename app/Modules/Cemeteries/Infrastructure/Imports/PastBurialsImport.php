<?php

namespace App\Modules\Cemeteries\Infrastructure\Imports;

use App\Models\Burial;
use App\Models\CemeteryRegion;
use App\Modules\Cemeteries\Application\Actions\ManageBurials;
use App\Modules\Cemeteries\Domain\Enums\Gender;
use App\Modules\Shared\Infrastructure\Excel\ExcelValues as Value;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Past burials from a spreadsheet, with the same headings the old system used:
 * name, death_date, burial_date, region, tomb (+ optional gender, place).
 * A row whose region is unknown, or that is already in the records, is skipped.
 */
class PastBurialsImport implements ToCollection, WithHeadingRow
{
    public int $imported = 0;

    /** @var list<string> */
    public array $skipped = [];

    public function __construct(private readonly ManageBurials $burials) {}

    public function collection(Collection $rows): void
    {
        $regions = CemeteryRegion::with('tombs')->get();

        foreach ($rows as $i => $row) {
            $line = $i + 2; // row 1 holds the headings
            $name = Value::text($row['name'] ?? null);
            if ($name === null) {
                continue;
            }

            $regionName = trim(preg_replace('/^(\d+)\s*/u', '$1 ', (string) Value::text($row['region'] ?? null)));
            $region = $regions->first(fn ($r) => $r->name === $regionName || ($regionName === 'مايو' && $r->name === '15 مايو'));
            if ($region === null) {
                $this->skipped[] = "سطر {$line}: المنطقة \"{$regionName}\" مش موجودة";

                continue;
            }

            $tombName = Value::text($row['tomb'] ?? null);
            $tomb = $tombName ? $region->tombs->firstWhere('name', $tombName) : null;
            $burialDate = Value::date($row['burial_date'] ?? null);

            if (Burial::query()->where('region_id', $region->id)->where('name', $name)->whereDate('burial_date', $burialDate)->exists()) {
                $this->skipped[] = "سطر {$line}: {$name} متسجل قبل كده";

                continue;
            }

            $place = Value::text($row['place'] ?? $row['burial_place'] ?? null);
            $this->burials->recordPast([
                'region_id' => $region->id,
                'tomb_id' => $tomb?->id,
                'name' => $name,
                'gender' => $this->gender(Value::text($row['gender'] ?? null)),
                'death_date' => Value::date($row['death_date'] ?? null),
                'burial_date' => $burialDate,
                'notes' => collect([$tombName && ! $tomb ? "المقبرة: {$tombName}" : null, $place ? "مكان الدفن: {$place}" : null])->filter()->implode(' · ') ?: null,
                'legacy_source' => null,
            ]);
            $this->imported++;
        }
    }

    private function gender(?string $value): ?string
    {
        return match (true) {
            $value === null => null,
            in_array($value, ['ذكر', 'male', 'm', 'رجل'], true) => Gender::Male->value,
            in_array($value, ['أنثى', 'انثى', 'female', 'f', 'سيدة'], true) => Gender::Female->value,
            default => null,
        };
    }
}
