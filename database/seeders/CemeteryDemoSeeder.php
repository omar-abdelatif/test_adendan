<?php

namespace Database\Seeders;

use App\Models\Burial;
use App\Models\CemeteryRegion;
use App\Models\CemeteryTomb;
use App\Models\Subscribers;
use App\Modules\Cemeteries\Application\Actions\ManageBurials;
use App\Modules\Cemeteries\Application\Actions\ManageTombs;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Local demo data for the cemeteries screens: chambers for the directory's tombs
 * and some burials, all through the real actions so every rule applies.
 * Refuses in production and when burials already exist.
 *
 *   php artisan db:seed --class=CemeteryDemoSeeder
 */
class CemeteryDemoSeeder extends Seeder
{
    /** tomb name => [chambers, burials as [chamber, gender, places]] */
    private const PLAN = [
        'مقبرة أدندان الكبرى' => [22, 'lahd-male', 8],
        'مقبرة أدندان سيدات' => [22, 'lahd-female', 13],
        'مقابر القطامية' => [8, 'eyes', [[1, 'male', 2], [1, 'male', 1], [1, 'female', 0], [2, 'female', 6], [3, 'male', 4], [5, 'male', 6], [6, 'female', 2], [7, 'male', 1]]],
        'مقبرة القطامية سيدات' => [6, 'eyes', [[1, 'female', 6], [2, 'female', 6], [3, 'female', 4], [4, 'female', 3], [5, 'female', 2]]],
        'مقابر أكتوبر' => [30, 'lahd-mixed', 11],
        'مقبرة أكتوبر الجديدة' => [20, 'lahd-male', 3],
        'مقبرة الغفير' => [4, 'eyes', [[1, 'male', 6], [2, 'male', 6], [3, 'male', 5], [4, 'male', 3]]],
        'مقبرة الغفير سيدات' => [3, 'eyes', [[1, 'female', 6], [2, 'female', 5], [3, 'female', 4]]],
        'مقبرة زينهم' => [4, 'eyes', [[1, 'male', 6], [2, 'male', 4], [3, 'male', 3], [4, 'male', 1]]],
        'مقبرة الفيوم' => [6, 'eyes', [[1, 'male', 2], [2, 'female', 1], [3, 'male', 1], [5, 'female', 1]]],
        'مقبرة العين السخنة' => [24, 'lahd-mixed', 2],
    ];

    private const MEN = ['سيد عبد الحليم نور', 'حسن طه دهب', 'محمود عوض كرار', 'عبده سيد سليم', 'عثمان إدريس كاشف', 'جمعة حسن بشير', 'صالح محمد فرج', 'إدريس علي شندي', 'كمال عوض داود', 'يوسف حامد جعفر', 'طه عثمان سنوسي', 'مصطفى سيد حسنين'];

    private const WOMEN = ['زينب محمد صالح', 'آمنة عوض شندي', 'فاطمة إدريس نور', 'خديجة سيد كرار', 'نفيسة عثمان دهب', 'حليمة طه بشير', 'سعاد محمود فرج', 'رقية علي جعفر', 'هانم صالح سليم', 'بخيتة عوض كاشف'];

    private int $n = 0;

    public function run(ManageTombs $tombs, ManageBurials $burials): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('CemeteryDemoSeeder is for local demo data only.');
        }
        if (Burial::exists()) {
            throw new RuntimeException('There are burials already; the cemetery demo data is not added twice.');
        }

        $memberIds = Subscribers::where('status', 2)->limit(6)->pluck('id')->all();

        foreach (self::PLAN as $name => [$chambers, $kind, $plan]) {
            $tomb = CemeteryTomb::where('name', $name)->first();
            if (! $tomb) {
                continue;
            }
            $tombs->update($tomb, ['annual_cost' => $tomb->kind->value === 'lahd' ? 1500 : 3000], $chambers);
            $tomb->refresh();

            $list = is_int($plan)
                ? array_map(fn ($i, $number) => [$number, $kind === 'lahd-female' || ($kind === 'lahd-mixed' && $i % 3 === 0) ? 'female' : 'male', 1], range(0, $plan - 1), array_slice([...range(1, $chambers, 2), ...range(2, $chambers, 2)], 0, $plan))
                : $plan;

            foreach ($list as [$chamber, $gender, $places]) {
                if ($places === 0) {
                    continue;
                }
                $burials->record([
                    'region_id' => $tomb->region_id,
                    'tomb_id' => $tomb->id,
                    'chamber_id' => $tomb->chambers->firstWhere('number', $chamber)->id,
                    'subscriber_id' => $this->n % 9 === 0 ? ($memberIds[$this->n % max(1, count($memberIds))] ?? null) : null,
                    'name' => $this->name($gender),
                    'gender' => $gender,
                    'places' => $places,
                    'age' => 55 + ($this->n * 7) % 35,
                    'death_place' => ['القاهرة', 'الجيزة', 'أسوان'][$this->n % 3],
                    'death_date' => $this->date(1)->toDateString(),
                    'burial_date' => $this->date(0)->toDateString(),
                    'washer' => 'الحاج إدريس صالح',
                    'carrier' => 'عربية الجمعية',
                ]);
                $this->n++;
            }
        }

        $village = CemeteryRegion::where('is_village', true)->first();
        foreach (range(1, 12) as $i) {
            $gender = $i % 2 ? 'male' : 'female';
            $burials->record(['region_id' => $village->id, 'name' => $this->name($gender), 'gender' => $gender, 'burial_date' => $this->date(0)->subYears($i % 4)->toDateString()]);
            $this->n++;
        }
    }

    private function name(string $gender): string
    {
        $names = $gender === 'male' ? self::MEN : self::WOMEN;

        return $names[$this->n % count($names)].($this->n >= count($names) ? ' '.['الكبير', 'الصغير', 'الأول', 'التاني'][intdiv($this->n, count($names)) % 4] : '');
    }

    /** Burial dates spread over the last few years, newest first for the first rows. */
    private function date(int $daysBefore): CarbonImmutable
    {
        return CarbonImmutable::parse('2026-11-20')->subDays(($this->n * 37) % 1400 + $daysBefore);
    }
}
