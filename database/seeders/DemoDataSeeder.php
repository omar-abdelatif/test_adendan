<?php

namespace Database\Seeders;

use App\Modules\Finance\Domain\Enums\Fund;
use Carbon\CarbonImmutable;
use Database\Seeders\Concerns\GuardsDemoData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Fills an empty local database with believable demo data so every dashboard
 * screen has something to show. Every name and number is made up.
 *
 *   php artisan db:seed --class=DemoDataSeeder
 *
 * It refuses to run in production or on a database that already has members.
 * It is not called from DatabaseSeeder.
 */
class DemoDataSeeder extends Seeder
{
    use GuardsDemoData;

    private const TODAY = '2026-11-21';

    private const COST = [2023 => 120, 2024 => 160, 2025 => 200, 2026 => 240, 2027 => 300];

    private const SMS_FEE = 40;

    /** @var list<array<string, mixed>> */
    private array $tx = [];

    /** @var array<int, array<string, mixed>> */
    private array $members = [];

    private int $safe = 0;

    private int $bank = 0;

    /** @var array<int, int> member_id => subscribers.id */
    private array $rowIds = [];

    /** dues paid today in the scripted "today" block, so the generator leaves them open */
    private const PAID_TODAY = [1056 => ['2027'], 1203 => ['2026', '2027'], 1187 => ['2027'], 1290 => ['2025', '2026']];

    public function run(): void
    {
        $this->ensureDemoDataAllowed();
        if (DB::table('subscribers')->exists()) {
            $this->command->warn('The database already has members; demo data was not added.');

            return;
        }

        mt_srand(2027);
        $this->copyImages();

        DB::transaction(function () {
            $this->costYears();
            $this->makeMembers();
            $this->insertMembers();
            $this->subscriptionDuesAndPayments();
            $this->donationDues();
            $this->voluntaryDonations();
            $this->donorsAndOuterDonations();
            $this->expenses();
            $this->smsSubscribers();
            $this->today();
            $this->transfersAndBalances();
            $this->directory();
            $this->content();
            $this->staffAndRoles();
            $this->requestsOcrAndActivity();
        });

        $this->command->info(sprintf('Demo data added: %d members, %d transactions.', count($this->members), count($this->tx)));
    }

    /* ------------------------------------------------------------------ helpers */

    private function r(int $min, int $max): int
    {
        return mt_rand($min, $max);
    }

    private function chance(float $p): bool
    {
        return mt_rand() / mt_getrandmax() < $p;
    }

    /** @param array<int|string, int> $weights */
    private function weighted(array $weights): int|string
    {
        $total = array_sum($weights);
        $pick = mt_rand(1, $total);
        foreach ($weights as $key => $w) {
            $pick -= $w;
            if ($pick <= 0) {
                return $key;
            }
        }

        return array_key_first($weights);
    }

    /**
     * @template T
     *
     * @param  list<T>  $list
     * @return T
     */
    private function pick(array $list): mixed
    {
        return $list[array_rand($list)];
    }

    private function at(string $date, ?string $time = null): string
    {
        return $date.' '.($time ?? sprintf('%02d:%02d:00', $this->r(9, 15), $this->r(0, 59)));
    }

    /** A random date inside [from, to). */
    private function dateBetween(string $from, string $to): string
    {
        $a = CarbonImmutable::parse($from)->timestamp;
        $b = CarbonImmutable::parse($to)->timestamp - 86400;

        return date('Y-m-d', $this->r($a, max($a, $b)));
    }

    private function fiscalYear(string $date): int
    {
        $d = CarbonImmutable::parse($date);

        return $d->month >= 7 ? $d->year + 1 : $d->year;
    }

    /**
     * One row for payment_transactions, the same shape Ledger::record writes.
     */
    private function tx(string $datetime, string $type, string $direction, int $amount, string $item, array $extra = []): void
    {
        $online = ($extra['channel'] ?? null) === 'دفع اونلاين';
        $this->tx[] = [
            'member_id' => $extra['member_id'] ?? null,
            'item' => $item,
            'amount' => $amount,
            'inv_no' => null,
            'payment_cat' => $extra['cat'] ?? 'كلي',
            'payment_date' => substr($datetime, 0, 10),
            'payment_method' => $extra['method'] ?? 'كاش',
            'transaction_type' => $type,
            'transaction_method' => $extra['channel'] ?? 'المقر',
            'paymob_intention_id' => $online ? 'pi_demo_'.Str::lower(Str::random(10)) : null,
            'paymob_transaction_id' => $online ? (string) $this->r(400000000, 499999999) : null,
            'paymob_status' => $online ? 'paid' : null,
            'transaction_cat' => $direction,
            'account' => array_key_exists('account', $extra) ? $extra['account'] : ($online ? null : 'safe'),
            'proof_img' => $extra['proof'] ?? null,
            'created_at' => $datetime,
            'updated_at' => $datetime,
            '_inv' => $extra['inv'] ?? (! $online && $direction === 'ايداع' && ($extra['channel'] ?? 'المقر') === 'المقر'),
            '_donation' => $extra['donation'] ?? null,
        ];
    }

    private function copyImages(): void
    {
        $src = base_path('../design-mockups/assets');
        if (! File::isDirectory($src)) {
            $src = public_path('assets/images/nubian');
        }
        $targets = [
            'assets/images/news-imgs' => ['drummers', 'board', 'cemeteries', 'weddings', 'map-3d', 'news', 'subscriptions', 'nubian-house', 'door-scene', 'nile-landscape', 'hands', 'craftsmen'],
            'assets/images/news-imgs/news-thumbnails' => ['drummers', 'weddings', 'nubian-house', 'door-scene', 'thumb-domes', 'village-strip'],
            'assets/images/border-photos' => ['logo-arch'],
        ];
        foreach ($targets as $dir => $names) {
            File::ensureDirectoryExists(public_path($dir));
            foreach ($names as $name) {
                $from = "$src/$name.jpg";
                $to = public_path("$dir/demo-$name.jpg");
                if (File::exists($from) && ! File::exists($to)) {
                    File::copy($from, $to);
                }
            }
        }
    }

    /* ------------------------------------------------------------------ finance setup */

    private function costYears(): void
    {
        foreach (self::COST as $year => $cost) {
            $at = sprintf('%d-07-01 09:00:00', $year - 1);
            DB::table('cost_years')->insert(['year' => $year, 'cost' => $cost, 'created_at' => $at, 'updated_at' => $at]);
        }
    }

    /* ------------------------------------------------------------------ members */

    private function makeMembers(): void
    {
        $fixed = [
            [1187, 'عثمان سيد كاشف', 'm', '01001234567', 'عين شمس، القاهرة', 'محاسب', 'عامل', 1, '1985-03-15', '2022-11-12'],
            [1342, 'فاطمة عبده جمال الدين', 'f', '01112345678', 'المعادي، القاهرة', 'مدرسة', 'عامل', 1, '1990-07-22', '2022-11-10'],
            [1203, 'حسن محمد بشير', 'm', '01223456789', 'نصر النوبة، أسوان', 'مهندس', 'عامل', 1, '1978-11-05', '2022-11-11'],
            [1056, 'يوسف إبراهيم كباشي', 'm', '01098765432', 'شبرا، القاهرة', 'على المعاش', 'عامل', 1, '1965-02-28', '2022-11-10'],
            [1290, 'أمل حسين فرج', 'f', '01156789012', 'حلوان، القاهرة', 'طبيبة', 'إنتساب', 1, '1994-12-01', '2023-09-04'],
            [1078, 'عبد الرحيم صالح شندي', 'm', '01011122233', 'الهرم، الجيزة', 'أعمال حرة', 'عامل', 1, '1959-09-09', '2022-11-10'],
            [1129, 'نادية عثمان داود', 'f', '01287654321', 'مدينة نصر، القاهرة', 'موظف حكومي', 'عامل', 3, '1988-04-14', '2022-11-12'],
            [1265, 'خديجة محمود سليم', 'f', '01148887766', null, 'ربة منزل', 'إنتساب', 0, '1982-06-30', '2023-02-15'],
            [1034, 'سيد عبد الحليم نور', 'm', '01066554433', 'أدندان، نصر النوبة', 'على المعاش', 'عامل', 2, '1948-03-03', '2022-11-10'],
            [1402, 'محمد حسن أدهم', 'm', '01277788899', 'عين شمس، القاهرة', 'مبرمج', 'عامل', 1, '1996-09-12', '2024-01-20'],
            [1150, 'زينب عوض كرار', 'f', '01009988776', 'أسوان', 'مدرسة', 'عامل', 1, '1977-05-05', '2022-11-12'],
        ];

        $maleFirst = ['محمد', 'أحمد', 'حسن', 'عثمان', 'يوسف', 'مصطفى', 'عبد الرحيم', 'سيد', 'إبراهيم', 'صالح', 'عادل', 'هاني', 'جمال', 'طه', 'عمر', 'خالد', 'كمال', 'إدريس', 'عوض', 'حامد', 'محمود', 'علي', 'ياسر', 'نبيل', 'مجدي', 'رمضان', 'صلاح', 'أيمن', 'شريف', 'سامي', 'عبد الله', 'بشير', 'طارق', 'كريم', 'إسلام', 'عمرو'];
        $femaleFirst = ['فاطمة', 'أمل', 'نادية', 'خديجة', 'زينب', 'سامية', 'عزة', 'صفية', 'نجوى', 'منى', 'رشا', 'سعاد', 'هالة', 'نجلاء', 'سلوى', 'هدى', 'مريم', 'آمنة', 'حنان', 'إيمان', 'رحاب', 'سمية'];
        $fathers = ['محمد', 'حسن', 'عثمان', 'إبراهيم', 'صالح', 'عبد الله', 'سيد', 'محمود', 'علي', 'عوض', 'حامد', 'إدريس', 'جمال', 'يوسف', 'عبد الحليم', 'مصطفى', 'كمال', 'طه'];
        $families = ['كاشف', 'بشير', 'كباشي', 'فرج', 'شندي', 'داود', 'عوض', 'سليم', 'نور', 'أدهم', 'كرار', 'إدريس', 'صالح', 'حامد', 'جمال الدين', 'عبد المجيد', 'الطيب', 'دهب', 'سنوسي', 'مرغني', 'جعفر', 'شاذلي', 'حسنين', 'فكري'];
        $places = [
            ['عين شمس، القاهرة', 16], ['المطرية، القاهرة', 12], ['شبرا، القاهرة', 10], ['حلوان، القاهرة', 8], ['المعادي، القاهرة', 7], ['مدينة نصر، القاهرة', 7], ['الزيتون، القاهرة', 5], ['حدائق القبة، القاهرة', 4],
            ['الهرم، الجيزة', 7], ['فيصل، الجيزة', 5], ['إمبابة، الجيزة', 3], ['أسوان', 9], ['كوم أمبو، أسوان', 4], ['دراو، أسوان', 2], ['نصر النوبة، أسوان', 4], ['أدندان، نصر النوبة', 3], ['الإسكندرية', 3], ['الأقصر', 2], ['السويس', 1],
        ];
        $jobs = ['موظف حكومي' => 382, 'على المعاش' => 297, 'مدرس' => 214, 'أعمال حرة' => 188, 'مهندس' => 126, 'محاسب' => 119, 'طبيب' => 64, 'سائق' => 60, 'فني كهرباء' => 50, 'طالب' => 60, 'ممرض' => 40, 'محامي' => 30, 'مزارع' => 30, 'ربة منزل' => 90];
        $ages = [[19, 24, 118], [25, 34, 362], [35, 44, 471], [45, 54, 398], [55, 64, 286], [65, 91, 169]];
        $quals = ['بكالوريوس تجارة', 'ليسانس آداب', 'بكالوريوس هندسة', 'دبلوم صنايع', 'دبلوم تجارة', 'ثانوية عامة', 'بكالوريوس تربية', 'بكالوريوس طب', 'ليسانس حقوق', 'بكالوريوس علوم'];

        $usedMobiles = [];
        $usedSsn = [];
        $usedNames = [];
        foreach ($fixed as $f) {
            $usedMobiles[(int) $f[3]] = true;
            $usedNames[$f[1]] = true;
        }

        $member = function (int $id, string $name, string $sex, ?string $mobile, ?string $address, string $job, string $type, int $status, string $birth, string $joined) use (&$usedSsn, $quals): array {
            $b = CarbonImmutable::parse($birth);
            $gov = str_contains((string) $address, 'أسوان') || str_contains((string) $address, 'النوبة') ? '28' : (str_contains((string) $address, 'الجيزة') ? '21' : '01');
            do {
                $serial = sprintf('%03d', $this->r(0, 999)).($sex === 'm' ? $this->pick(['1', '3', '5', '7', '9']) : $this->pick(['2', '4', '6', '8']));
                $ssn = ($b->year >= 2000 ? '3' : '2').$b->format('ymd').$gov.$serial.$this->r(1, 9);
            } while (isset($usedSsn[$ssn]));
            $usedSsn[$ssn] = true;

            return [
                'member_id' => $id, 'name' => $name, 'sex' => $sex, 'mobile' => $mobile, 'address' => $address, 'job' => $job, 'type' => $type,
                'status' => $status, 'birth' => $birth, 'joined' => $joined, 'ssn' => $ssn,
                'qual' => $job === 'طالب' ? 'ثانوية عامة' : $this->pick($quals),
                'marital' => $b->age < 27 ? 'أعزب' : ($b->age > 68 && $this->chance(.3) ? 'أرمل' : ($this->chance(.85) ? 'متزوج' : 'أعزب')),
            ];
        };

        foreach ($fixed as $f) {
            $this->members[$f[0]] = $member($f[0], $f[1], $f[2], $f[3], $f[4], $f[5], $f[6], $f[7], $f[8], $f[9]);
        }

        $nextId = 1001;
        $target = 860;
        $placeWeights = array_combine(array_keys($places), array_column($places, 1));
        while (count($this->members) < $target) {
            while (isset($this->members[$nextId]) || $nextId === 1811) {
                $nextId++;
            }
            $sex = $this->chance(.42) ? 'f' : 'm';
            do {
                $name = ($sex === 'm' ? $this->pick($maleFirst) : $this->pick($femaleFirst)).' '.$this->pick($fathers).' '.$this->pick($families);
            } while (isset($usedNames[$name]));
            $usedNames[$name] = true;

            [$amin, $amax] = $ages[$this->weighted(array_column($ages, 2))];
            $birth = CarbonImmutable::parse(self::TODAY)->subYears($this->r($amin, $amax))->subDays($this->r(0, 364))->toDateString();
            $job = (string) $this->weighted($jobs);
            if ($sex === 'f' && in_array($job, ['سائق', 'مزارع'], true)) {
                $job = 'ربة منزل';
            }
            $address = $this->chance(.05) ? null : $places[$this->weighted($placeWeights)][0];
            do {
                $mobile = '01'.$this->pick(['0', '1', '2', '5']).sprintf('%08d', $this->r(0, 99999999));
            } while (isset($usedMobiles[(int) $mobile]));
            $usedMobiles[(int) $mobile] = true;

            $status = (int) $this->weighted([1 => 80, 0 => 9, 3 => 2, 2 => 8]);
            // most members registered when the system started (Nov 2022); the rest joined over time
            $joined = $nextId < 1300 ? $this->dateBetween('2022-11-01', '2022-12-31') : $this->dateBetween('2023-01-01', '2026-11-20');
            if ($status === 2 && $joined > '2025-06-30') {
                $status = 1;
            }
            $type = $this->chance(.16) ? 'إنتساب' : 'عامل';

            $this->members[$nextId] = $member($nextId, $name, $sex, $mobile, $address, $job, $type, $status, $birth, $joined);
            $nextId++;
        }

        // joined this month, including the new member paid in "today"
        $this->members[1811] = $member(1811, 'مصطفى عبد الله عوض', 'm', '01544332211', 'كوم أمبو، أسوان', 'فني كهرباء', 'عامل', 1, '2000-01-18', self::TODAY);
        ksort($this->members);
    }

    private function insertMembers(): void
    {
        $tombs = ['مقبرة أدندان الكبرى', 'مقابر القطامية', 'مقبرة الغفير', 'مقابر أكتوبر'];
        $rows = [];
        foreach ($this->members as $m) {
            $at = $this->at($m['joined'], $m['member_id'] === 1811 ? '10:05:00' : null);
            $rows[] = [
                'member_id' => $m['member_id'],
                'name' => $m['name'],
                'slug' => Str::slug($m['name']).'-'.$m['member_id'],
                'nickname' => $this->chance(.08) ? 'أبو '.$this->pick(['سيد', 'حسن', 'علي', 'محمد']) : null,
                'ssn' => (int) $m['ssn'],
                'address' => $m['address'],
                'educational_qualification' => $m['qual'],
                'qualification_date' => CarbonImmutable::parse($m['birth'])->addYears(22)->setDate(CarbonImmutable::parse($m['birth'])->year + 22, 6, 30)->toDateString(),
                'job' => $m['job'],
                'job_destination' => in_array($m['job'], ['على المعاش', 'ربة منزل', 'طالب'], true) ? null : $this->pick(['وزارة التربية والتعليم', 'شركة كهرباء القاهرة', 'بنك مصر', 'مستشفى عين شمس', 'هيئة السكة الحديد', 'شركة خاصة', 'مصلحة الضرائب']),
                'job_tel' => null,
                'job_address' => null,
                'home_tel' => $this->chance(.2) ? '02'.$this->r(22000000, 29999999) : null,
                'martial_status' => $m['marital'],
                'birthdate' => $m['birth'],
                'mobile_no' => (int) $m['mobile'],
                'another_mobile_no' => $this->chance(.15) ? '01'.$this->r(0, 2).sprintf('%08d', $this->r(0, 99999999)) : null,
                'status' => $m['status'],
                'tomb_name' => $m['status'] === 2 ? $this->pick($tombs) : null,
                'membership_type' => $m['type'],
                'active_sms' => 0,
                'created_at' => $at,
                'updated_at' => $at,
            ];
        }
        foreach (array_chunk($rows, 300) as $chunk) {
            DB::table('subscribers')->insert($chunk);
        }
        $this->rowIds = DB::table('subscribers')->pluck('id', 'member_id')->map(fn ($v) => (int) $v)->all();
    }

    /* ------------------------------------------------------------------ dues */

    private function subscriptionDuesAndPayments(): void
    {
        $monthWeights2027 = [7 => 308, 8 => 237, 9 => 216, 10 => 194, 11 => 163];
        $dues = [];
        foreach ($this->members as $id => $m) {
            if ($id === 1811) {
                continue; // joins today
            }
            $first = max(2023, $this->fiscalYear($m['joined']));
            $last = match ($m['status']) {
                1 => 2027,
                2 => 2025,
                default => 2026,
            };
            for ($year = $first; $year <= $last; $year++) {
                $cost = self::COST[$year];
                $paid = $cost;
                $recent = $last - $year;
                if (in_array((string) $year, self::PAID_TODAY[$id] ?? [], true)) {
                    $paid = 0;
                } elseif ($m['status'] === 0 && $recent < $this->r(1, 3)) {
                    $paid = 0;
                } elseif ($m['status'] === 3 && $recent < 2) {
                    $paid = 0;
                } elseif ($year === 2027) {
                    $paid = $this->chance(.62) ? $cost : ($this->chance(.05) ? 150 : 0);
                } elseif ($m['status'] === 1) {
                    $roll = mt_rand(1, 100);
                    $unpaid = $year === 2026 ? 12 : 5;
                    $paid = $roll <= $unpaid ? 0 : ($roll <= $unpaid + 4 ? intdiv($cost, 2) : $cost);
                }
                if ($id === 1342 && $year === 2027) {
                    $paid = 0;
                }
                $dues[] = ['member_id' => $id, 'item' => (string) $year, 'total_amount' => $cost, 'amount_paid' => (string) $paid, 'amount_remaining' => (string) ($cost - $paid), 'transaction_type' => 'إشتراك',
                    'created_at' => sprintf('%d-07-01 08:00:00', $year - 1), 'updated_at' => sprintf('%d-07-01 08:00:00', $year - 1)];

                if ($paid > 0) {
                    if ($year === 2027) {
                        $month = (int) $this->weighted($monthWeights2027);
                        $yy = $month >= 7 ? 2026 : 2027;
                        $date = max($m['joined'], $this->dateBetween(sprintf('%d-%02d-01', $yy, $month), $month === 11 ? '2026-11-21' : CarbonImmutable::create($yy, $month, 1)->addMonth()->toDateString()));
                    } else {
                        $from = max(sprintf('%d-07-01', $year - 1), $m['joined']);
                        $date = $this->dateBetween($from, sprintf('%d-04-30', $year));
                        if ($date < $from) {
                            $date = $from;
                        }
                    }
                    $online = $year >= 2026 && $this->chance(.12);
                    $this->tx($this->at($date), $year === $first && $m['joined'] >= '2023-01-01' ? 'إشتراك جديد' : 'إشتراك', 'ايداع', $paid, (string) $year, [
                        'member_id' => $id, 'cat' => $paid < $cost ? 'جزئي' : 'كلي',
                        'method' => $online ? $this->pick(['محفظة', 'فيزا']) : 'كاش', 'channel' => $online ? 'دفع اونلاين' : 'المقر',
                    ]);
                }
            }
        }
        foreach (array_chunk($dues, 500) as $chunk) {
            DB::table('dues')->insert($chunk);
        }
    }

    private function donationDues(): void
    {
        $dues = [];
        $charges = [['تبرع تنمية', 200, '2025-11-01', .5], ['ص.مقر', 100, '2026-03-01', .35]];
        foreach ($this->members as $id => $m) {
            if ($m['status'] !== 1 || $id === 1811) {
                continue;
            }
            foreach ($charges as [$item, $amount, $date, $p]) {
                if ($m['joined'] > $date) {
                    continue;
                }
                $paid = $this->chance($p) ? $amount : ($this->chance(.15) ? intdiv($amount, 2) : 0);
                if ($id === 1342) {
                    $paid = $item === 'تبرع تنمية' ? 100 : 0;
                }
                $dues[] = ['member_id' => $id, 'item' => $item, 'total_amount' => $amount, 'amount_paid' => (string) $paid, 'amount_remaining' => (string) ($amount - $paid), 'transaction_type' => 'تبرعات', 'created_at' => "$date 08:00:00", 'updated_at' => "$date 08:00:00"];
                if ($paid > 0) {
                    $day = $this->dateBetween($date, CarbonImmutable::parse($date)->addMonths(5)->toDateString());
                    $this->donation($id, $item, $paid, $day, $paid < $amount ? 'جزئي' : 'كلي');
                }
            }
        }
        foreach (array_chunk($dues, 500) as $chunk) {
            DB::table('dues')->insert($chunk);
        }
    }

    private function donation(int $memberId, string $category, int $amount, string $date, string $cat = 'كلي', ?string $destination = null, ?string $time = null): void
    {
        $at = $this->at($date, $time);
        $donationId = DB::table('donations')->insertGetId([
            'member_id' => $memberId, 'subscribers_id' => $this->rowIds[$memberId], 'invoice_no' => null, 'amount' => $amount,
            'donation_type' => 'مادي', 'other_donation' => null, 'donation_destination' => $destination, 'donation_category' => $category,
            'payment_date' => $date, 'created_at' => $at, 'updated_at' => $at,
        ]);
        $this->tx($at, 'تبرعات', 'ايداع', $amount, $category, ['member_id' => $memberId, 'cat' => $cat, 'donation' => $donationId]);
    }

    private function voluntaryDonations(): void
    {
        $kinds = ['ص.سيارة' => 'سيارة نقل الموتى', 'مقابر قديمة' => 'تجديد مقابر 15 مايو', 'ص.مقر' => 'صندوق المقر', 'تبرع إنتساب' => 'تبرع سنوي', 'أخرى' => 'كسوة الشتاء'];
        $active = array_keys(array_filter($this->members, fn ($m) => $m['status'] === 1 && $m['member_id'] !== 1811));
        for ($i = 0; $i < 150; $i++) {
            $category = (string) array_rand($kinds);
            $this->donation($this->pick($active), $category, $this->pick([100, 200, 250, 300, 500, 500, 750, 1000]), $this->dateBetween('2024-01-01', self::TODAY), 'كلي', $kinds[$category]);
        }
    }

    private function donorsAndOuterDonations(): void
    {
        $donors = [
            ['م. سامي فرج', '01001112223', 'موسمي', 'رمضان والأعياد', '2024-03-12'], ['د. هالة عبد المجيد', '01223344556', 'منتظم', 'شهري', '2024-05-02'],
            ['مؤسسة النيل للتنمية', '0225671234', 'منتظم', 'ربع سنوي', '2024-09-18'], ['الحاج عبد الله كرار', '01112223334', 'موسمي', 'عيد الأضحى', '2025-06-01'],
            ['أ. منى الطيب', '01556677889', 'منتظم', 'شهري', '2025-01-20'], ['شركة أسوان للمقاولات', '0973123456', 'موسمي', 'مرة واحدة', '2025-10-10'],
            ['م. طارق دهب', '01009090909', 'موسمي', 'رمضان', '2024-03-20'], ['د. سمير سنوسي', '01223232311', 'منتظم', 'شهري', '2025-02-01'],
            ['أ. ليلى مرغني', '01111888777', 'موسمي', 'الأعياد', '2025-04-05'], ['الحاج فوزي جعفر', '01558765432', 'موسمي', 'مرة واحدة', '2026-01-15'],
        ];
        $dest = ['صندوق المقر', 'كسوة الشتاء', 'شنط رمضان', 'تجديد مقابر 15 مايو', 'سيارة نقل الموتى', 'كفالة أسر'];
        foreach ($donors as $i => $d) {
            $at = $this->at($d[4]);
            $donorId = DB::table('donators')->insertGetId(['name' => $d[0], 'mobile_number' => (int) $d[1], 'donator_type' => $d[2], 'duration' => $d[3], 'created_at' => $at, 'updated_at' => $at]);
            $count = $d[2] === 'منتظم' ? $this->r(5, 9) : $this->r(1, 4);
            for ($k = 0; $k < $count; $k++) {
                $date = $this->dateBetween($d[4], '2026-11-20');
                $amount = $i === 2 ? 15000 : $this->pick([500, 700, 1000, 1500, 2000, 2500, 3000, 5000]);
                $method = $this->pick(['كاش', 'كاش', 'كارت', 'محفظة']);
                $this->outerDonation($donorId, $d, $amount, $date, $method, $this->pick($dest));
            }
        }
    }

    /** @param array{0: string, 1: string, 2: string, 3: string, 4: string} $donor */
    private function outerDonation(int $donorId, array $donor, int $amount, string $date, string $method, string $destination, ?string $time = null): void
    {
        $at = $this->at($date, $time);
        DB::table('outer_donations')->insert([
            'invoice_id' => $this->r(40000, 58000), 'amount' => $amount, 'donation_destination' => $destination, 'donators_id' => $donorId,
            'name' => $donor[0], 'donator_type' => $donor[2], 'donation_type' => 'مادي', 'payment_method' => $method, 'duration' => $donor[3],
            'created_at' => $at, 'updated_at' => $at,
        ]);
        $this->tx($at, 'تبرعات خارجية', 'ايداع', $amount, 'تبرع خارجي: '.$donor[0], ['method' => $method]);
    }

    private function expenses(): void
    {
        $others = ['أدوات نظافة', 'صيانة سماعات القاعة', 'شاي وقهوة للعزاء', 'طباعة إيصالات', 'صيانة مراوح', 'كراسي للقاعة', 'إصلاح سباكة', 'لمبات وكهرباء القاعة'];
        for ($month = CarbonImmutable::parse('2023-01-01'); $month->lessThan(CarbonImmutable::parse('2026-11-01')); $month = $month->addMonth()) {
            $bills = [['كهرباء', $this->r(600, 1300)], ['مياه', $this->r(150, 350)], ['تلفون ارضي', $this->r(180, 240)], ['انترنت ارضي', 450]];
            if ($this->chance(.5)) {
                $bills[] = ['غاز', $this->r(80, 200)];
            }
            for ($k = 0, $n = $this->r(1, 3); $k < $n; $k++) {
                $bills[] = ['أخرى', $this->r(2, 30) * 100];
            }
            foreach ($bills as [$category, $amount]) {
                $this->expense($category, $amount, $month->addDays($this->r(1, 26))->toDateString(), $category === 'أخرى' ? $this->pick($others) : null);
            }
        }
        // November 2026 so far (today's bills are added in today())
        foreach ([['انترنت ارضي', 450, '2026-11-15'], ['غاز', 180, '2026-11-10'], ['تلفون ارضي', 220, '2026-11-02'], ['أخرى', 640, '2026-11-05', 'أدوات نظافة']] as $b) {
            $this->expense($b[0], $b[1], $b[2], $b[3] ?? null);
        }
    }

    private function expense(string $category, int $amount, string $date, ?string $other = null, ?string $time = null): void
    {
        $at = $this->at($date, $time);
        DB::table('miscellaneouses')->insert(['category' => $category, 'other_category' => $other, 'invoice_img' => null, 'amount' => $amount, 'created_at' => $at, 'updated_at' => $at]);
        $this->tx($at, 'نثريات', 'سحب', $amount, $other ? "نثريات: $other" : "نثريات: $category");
    }

    private function smsSubscribers(): void
    {
        $active = array_keys(array_filter($this->members, fn ($m) => $m['status'] === 1 && $m['mobile'] && $m['member_id'] !== 1811 && $m['member_id'] !== 1129));
        shuffle($active);
        $rows = [];
        $add = function (?int $memberId, int $mobile, string $start) use (&$rows) {
            $end = CarbonImmutable::parse($start)->addYear()->subDay()->toDateString();
            $at = $this->at($start);
            $rows[] = ['member_id' => $memberId, 'mobile_no' => $mobile, 'amount' => self::SMS_FEE, 'subscription_start_date' => $start, 'subscription_expiry_date' => $end, 'active_sms' => $end >= self::TODAY ? 1 : 0, 'created_at' => $at, 'updated_at' => $at];
            $this->tx($at, 'اشتراك', 'ايداع', self::SMS_FEE, 'رسائل', ['member_id' => $memberId]);
        };
        foreach (array_slice($active, 0, 260) as $id) {
            $add($id, (int) $this->members[$id]['mobile'], $this->dateBetween('2025-09-01', '2026-11-20'));
        }
        for ($i = 0; $i < 14; $i++) {
            $add(null, (int) ('010'.$this->r(10000000, 99999999)), $this->dateBetween('2025-09-01', '2026-11-20'));
        }
        DB::table('sms_subscribers')->insert($rows);
        DB::table('subscribers')->whereIn('member_id', array_filter(array_column($rows, 'member_id')))->update(['active_sms' => 1]);
    }

    /* ------------------------------------------------------------------ today, 21 November 2026 */

    private function today(): void
    {
        $d = self::TODAY;
        $pay = function (int $id, string $year, int $amount, string $time, string $type = 'إشتراك', string $channel = 'المقر', string $method = 'كاش') use ($d) {
            DB::table('dues')->where(['member_id' => $id, 'item' => $year, 'transaction_type' => 'إشتراك'])
                ->update(['amount_paid' => DB::raw("amount_paid + $amount"), 'amount_remaining' => DB::raw("amount_remaining - $amount"), 'updated_at' => "$d $time"]);
            $due = DB::table('dues')->where(['member_id' => $id, 'item' => $year, 'transaction_type' => 'إشتراك'])->first();
            $this->tx("$d $time", $type, 'ايداع', $amount, $year, ['member_id' => $id, 'channel' => $channel, 'method' => $method, 'cat' => (int) $due->amount_remaining > 0 ? 'جزئي' : 'كلي']);
        };

        $pay(1056, '2027', 300, '09:12:00');
        $pay(1203, '2026', 240, '09:30:00', 'إشتراك', 'دفع اونلاين', 'محفظة');
        $pay(1203, '2027', 300, '09:30:30', 'إشتراك', 'دفع اونلاين', 'محفظة');
        DB::table('dues')->where(['member_id' => 1342, 'item' => 'تبرع تنمية'])->update(['amount_paid' => '200', 'amount_remaining' => '0']);
        $this->donation(1342, 'تبرع تنمية', 100, $d, 'كلي', 'تطوير المقر', '09:47:00');
        $this->expense('كهرباء', 1240, $d, null, '09:58:00');

        // a new member joins: first subscription and the yearly donation
        DB::table('dues')->insert(['member_id' => 1811, 'item' => '2027', 'total_amount' => 300, 'amount_paid' => '300', 'amount_remaining' => '0', 'transaction_type' => 'إشتراك', 'created_at' => "$d 10:05:00", 'updated_at' => "$d 10:05:00"]);
        $this->tx("$d 10:05:00", 'إشتراك جديد', 'ايداع', 300, '2027', ['member_id' => 1811]);
        $this->tx("$d 10:05:30", 'تبرع سنوي', 'ايداع', 500, 'تبرع سنوي', ['member_id' => 1811]);

        DB::table('sms_subscribers')->insert(['member_id' => 1129, 'mobile_no' => 1287654321, 'amount' => self::SMS_FEE, 'subscription_start_date' => $d, 'subscription_expiry_date' => '2027-11-20', 'active_sms' => 1, 'created_at' => "$d 10:15:00", 'updated_at' => "$d 10:15:00"]);
        DB::table('subscribers')->where('member_id', 1129)->update(['active_sms' => 1]);
        $this->tx("$d 10:15:00", 'اشتراك', 'ايداع', self::SMS_FEE, 'رسائل', ['member_id' => 1129]);

        $pay(1187, '2027', 300, '10:42:00');
        $this->expense('مياه', 310, $d, null, '11:05:00');
        $donorId = (int) DB::table('donators')->where('name', 'م. سامي فرج')->value('id');
        $this->outerDonation($donorId, ['م. سامي فرج', '', 'موسمي', 'رمضان والأعياد', ''], 2500, $d, 'كاش', 'صندوق المقر', '11:20:00');
        $this->donation(1078, 'ص.مقر', 1000, $d, 'كلي', 'صندوق المقر', '11:48:00');
        $pay(1290, '2025', 200, '12:10:00');
        $pay(1290, '2026', 190, '12:10:30');
        $this->expense('أخرى', 2750, $d, 'صيانة سماعات القاعة', '12:30:00');
    }

    /* ------------------------------------------------------------------ transfers, receipts, balances */

    private function transfersAndBalances(): void
    {
        // the bank paid for two big jobs this year
        $this->tx('2026-09-03 11:00:00', 'تحويلات', 'سحب', 23500, 'تحويل من البنك إلى الخزنة', ['account' => 'bank', 'channel' => 'تحويلات', 'proof' => 'withdraw-0903.jpg', 'inv' => false]);
        $this->tx('2026-09-03 11:00:30', 'تحويلات', 'ايداع', 23500, 'تحويل من البنك إلى الخزنة', ['account' => 'safe', 'channel' => 'تحويلات', 'proof' => 'withdraw-0903.jpg', 'inv' => false]);
        $this->expense('أخرى', 23500, '2026-09-04', 'تجديد مقابر 15 مايو', '10:00:00');
        $this->tx('2026-10-30 11:00:00', 'تحويلات', 'سحب', 15000, 'تحويل من البنك إلى الخزنة', ['account' => 'bank', 'channel' => 'تحويلات', 'proof' => 'withdraw-1030.jpg', 'inv' => false]);
        $this->tx('2026-10-30 11:00:30', 'تحويلات', 'ايداع', 15000, 'تحويل من البنك إلى الخزنة', ['account' => 'safe', 'channel' => 'تحويلات', 'proof' => 'withdraw-1030.jpg', 'inv' => false]);
        $this->expense('أخرى', 15000, '2026-10-31', 'صيانة المقر', '10:00:00');

        usort($this->tx, fn ($a, $b) => strcmp($a['created_at'], $b['created_at']));

        // walk the safe day by day; move surplus to the bank on the 25th of each month
        $all = [];
        $nextTransfer = CarbonImmutable::parse('2022-12-25 16:00:00');
        $flush = function (string $until) use (&$all, &$nextTransfer) {
            while ($nextTransfer->toDateTimeString() <= $until && $nextTransfer->lessThan(CarbonImmutable::parse('2026-11-01'))) {
                if ($this->safe > 30000) {
                    $amount = intdiv($this->safe - 20000, 5000) * 5000;
                    $at = $nextTransfer->toDateTimeString();
                    $proof = 'slip-'.$nextTransfer->format('Ymd').'.jpg';
                    $all[] = $this->transferRow($at, 'سحب', 'safe', $amount, $proof);
                    $all[] = $this->transferRow($nextTransfer->addSeconds(30)->toDateTimeString(), 'ايداع', 'bank', $amount, $proof);
                    $this->safe -= $amount;
                    $this->bank += $amount;
                }
                $nextTransfer = $nextTransfer->addMonth();
            }
        };
        foreach ($this->tx as $row) {
            $flush($row['created_at']);
            $all[] = $row;
            $sign = $row['transaction_cat'] === 'ايداع' ? 1 : -1;
            if ($row['account'] === 'safe') {
                $this->safe += $sign * $row['amount'];
            } elseif ($row['account'] === 'bank') {
                $this->bank += $sign * $row['amount'];
            }
        }
        // today's transfer leaves about forty thousand in the safe
        $amount = max(10000, intdiv($this->safe - 40000, 1000) * 1000);
        $all[] = $this->transferRow(self::TODAY.' 13:05:00', 'سحب', 'safe', $amount, 'deposit-slip.jpg');
        $all[] = $this->transferRow(self::TODAY.' 13:05:30', 'ايداع', 'bank', $amount, 'deposit-slip.jpg');
        $this->safe -= $amount;
        $this->bank += $amount;

        // receipt numbers in date order, office receipts only
        $inv = 30000;
        $donationReceipts = [];
        foreach ($all as &$row) {
            if ($row['_inv']) {
                $row['inv_no'] = ++$inv;
            }
            if ($row['_donation']) {
                $donationReceipts[$row['_donation']] = $row['inv_no'];
            }
            unset($row['_inv'], $row['_donation']);
        }
        unset($row);
        $this->tx = $all;
        foreach (array_chunk($all, 500) as $chunk) {
            DB::table('payment_transactions')->insert($chunk);
        }

        foreach ($donationReceipts as $donationId => $receipt) {
            DB::table('donations')->where('id', $donationId)->update(['invoice_no' => $receipt]);
        }

        $this->setBalance('total_safes', $this->safe);
        $this->setBalance('total_banks', $this->bank);
    }

    private function transferRow(string $at, string $direction, string $account, int $amount, string $proof): array
    {
        $label = $direction === 'سحب' && $account === 'safe' || $direction === 'ايداع' && $account === 'bank' ? 'تحويل من الخزنة إلى البنك' : 'تحويل من البنك إلى الخزنة';

        return [
            'member_id' => null, 'item' => $label, 'amount' => $amount, 'inv_no' => null, 'payment_cat' => 'كلي', 'payment_date' => substr($at, 0, 10),
            'payment_method' => 'كاش', 'transaction_type' => 'تحويلات', 'transaction_method' => 'تحويلات', 'paymob_intention_id' => null, 'paymob_transaction_id' => null,
            'paymob_status' => null, 'transaction_cat' => $direction, 'account' => $account, 'proof_img' => $proof, 'created_at' => $at, 'updated_at' => $at, '_inv' => false, '_donation' => null,
        ];
    }

    private function setBalance(string $table, int $amount): void
    {
        // The association's balance row; sponsorship and new-tomb funds keep their own rows.
        $id = DB::table($table)->where('fund', Fund::General->value)->orderBy('id')->value('id');
        $now = self::TODAY.' 13:05:30';
        $id
            ? DB::table($table)->where('id', $id)->update(['amount' => $amount, 'updated_at' => $now])
            : DB::table($table)->insert(['fund' => Fund::General->value, 'amount' => $amount, 'created_at' => $now, 'updated_at' => $now]);
    }

    /* ------------------------------------------------------------------ directory */

    /** Finds or creates a cemetery region the way the cemeteries migration names them ("15مايو" → "15 مايو"). */
    private function cemeteryRegion(string $name, string $now): int
    {
        $name = trim((string) preg_replace('/^(\d+)\s*/u', '$1 ', $name));
        $existing = DB::table('cemetery_regions')->where('name', $name)->value('id');
        if ($existing) {
            return (int) $existing;
        }

        $slug = Str::slug($name);
        if ($slug === '' || DB::table('cemetery_regions')->where('slug', $slug)->exists()) {
            $slug = 'region-'.(DB::table('cemetery_regions')->count() + 1);
        }

        return (int) DB::table('cemetery_regions')->insertGetId([
            'name' => $name, 'slug' => $slug, 'is_village' => $name === 'القرية', 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    private function directory(): void
    {
        $now = self::TODAY.' 09:00:00';
        $tombs = [
            ['مقبرة أدندان الكبرى', '15مايو', 'لحد', 'رجال', 'محمود التُربي', '01004455667'], ['مقبرة أدندان سيدات', '15مايو', 'لحد', 'سيدات', 'محمود التُربي', '01004455667'],
            ['مقابر القطامية', 'القطامية', 'عيون', 'مختلط', 'عبد الصبور حامد', '01117788990'], ['مقبرة الغفير', 'الغفير', 'عيون', 'رجال', 'رجب سعيد', '01228899001'],
            ['مقابر أكتوبر', 'أكتوبر', 'لحد', 'مختلط', 'فتحي عبد النبي', '01559900112'], ['مقبرة زينهم', 'زينهم', 'عيون', 'رجال', 'سعد الدين علي', '01001231231'],
            ['مقبرة العين السخنة', 'العين السخنة', 'لحد', 'مختلط', 'جمعة حسن', '01062626262'], ['مقابر القرية', 'القرية', 'لحد', 'مختلط', 'إدريس صالح', '01278787878'],
            ['مقبرة الفيوم', 'الفيوم', 'عيون', 'مختلط', 'شعبان محمود', '01145454545'], ['مقبرة القطامية سيدات', 'القطامية', 'عيون', 'سيدات', 'عبد الصبور حامد', '01117788990'],
            ['مقبرة أكتوبر الجديدة', 'أكتوبر', 'لحد', 'رجال', 'فتحي عبد النبي', '01559900112'], ['مقبرة الغفير سيدات', 'الغفير', 'عيون', 'سيدات', 'رجب سعيد', '01228899001'],
        ];
        foreach ($tombs as $t) {
            $location = 'https://maps.google.com/?q='.urlencode($t[0]);
            if (Schema::hasTable('tombs')) {
                // Before the cemeteries migration: the directory's own table.
                DB::table('tombs')->insert(['title' => $t[0], 'region' => $t[1], 'tomb_specialization' => $t[2], 'tomb_type' => $t[3], 'tomb_guard_name' => $t[4], 'tomb_guard_number' => (int) $t[5], 'location' => $location, 'created_at' => $now, 'updated_at' => $now]);

                continue;
            }
            // After it (a fresh database): one tombs table for the cemeteries module and the directory.
            DB::table('cemetery_tombs')->insert([
                'region_id' => $this->cemeteryRegion($t[1], $now),
                'name' => $t[0],
                'kind' => $t[2] === 'لحد' ? 'lahd' : 'eyes',
                'serves' => match ($t[3]) {
                    'سيدات' => 'women',
                    'رجال' => 'men',
                    default => 'mixed',
                },
                'location' => $location,
                'guard_name' => $t[4],
                'guard_phone' => $t[5],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $days = ['السبت', 'الأحد', 'الأثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة'];
        $grooms = ['أحمد عثمان كاشف', 'محمود صالح إدريس', 'كريم يوسف داود', 'عمرو حسين فرج', 'إسلام عوض سليم', 'طارق محمد شندي', 'شريف علي نور', 'ياسر حسن بشير', 'مصطفى كمال كرار', 'عمر سيد دهب', 'خالد جمال الطيب', 'أيمن طه حامد', 'هاني محمود سنوسي', 'محمد إبراهيم مرغني'];
        $fathers = ['حسن بشير', 'عبد الله كرار', 'سيد نور', 'محمد أدهم', 'عثمان داود', 'إبراهيم كباشي', 'صالح شندي', 'جمال عوض', 'طه فرج', 'كمال دهب', 'علي جعفر', 'حامد سليم', 'محمود الطيب', 'إدريس صالح'];
        $halls = ['قاعة النخيل، عين شمس', 'مقر الجمعية، المطرية', 'دار المناسبات، الهرم', 'أدندان، نصر النوبة', 'قاعة الماسة، مدينة نصر', 'نادي المعلمين، أسوان'];
        $dates = ['2026-11-26', '2026-11-27', '2026-12-03', '2026-12-12', '2026-12-17', '2026-12-25', '2027-01-07', '2027-01-14', '2026-10-15', '2026-10-01', '2026-09-17', '2026-08-27', '2026-08-13', '2026-07-30'];
        foreach ($dates as $i => $date) {
            $day = $days[(CarbonImmutable::parse($date)->dayOfWeek + 1) % 7];
            DB::table('weddings')->insert(['day' => $day, 'date' => $date, 'groom_name' => $grooms[$i], 'pride_father_name' => $fathers[$i], 'address' => $halls[$i % count($halls)], 'from_time' => $this->pick(['18:00', '19:00', '19:30', '20:00']), 'to_time' => $this->pick(['22:00', '23:00', '23:30', '00:00']), 'created_at' => $now, 'updated_at' => $now]);
        }

        $crafts = ['نجار', 'نقاش', 'سباك', 'كهربائي', 'فني', 'أخرى'];
        $otherCrafts = ['تكييفات', 'ألوميتال', 'محارة', 'سيراميك', 'دش وستالايت'];
        $areas = ['عين شمس', 'المطرية', 'شبرا', 'حلوان', 'مدينة نصر', 'الهرم', 'المعادي', 'أسوان', 'فيصل', 'الزيتون'];
        $names = ['حمدي كرار', 'صلاح إدريس', 'رمضان عوض', 'ياسر داود', 'عادل بشير', 'نبيل شندي', 'عمر كاشف', 'مجدي نور', 'سعيد فرج', 'جمال سليم', 'حسين دهب', 'محمد الطيب', 'كمال جعفر', 'سامح سنوسي', 'أشرف مرغني', 'وليد حامد', 'رضا أدهم', 'عاطف كباشي', 'ممدوح صالح', 'شعبان حسنين', 'فتحي شاذلي', 'ناصر فكري', 'هشام عبد المجيد', 'إيهاب كرار', 'مصطفى دهب', 'سيد عوض', 'خالد نور', 'طه بشير', 'علاء شندي', 'زكريا داود'];
        foreach ($names as $name) {
            $craft = $this->pick($crafts);
            DB::table('workers')->insert(['name' => $name, 'phone_number' => (int) ('1'.$this->pick(['0', '1', '2', '5']).sprintf('%08d', $this->r(0, 99999999))), 'craft' => $craft, 'other_craft' => in_array($craft, ['فني', 'أخرى'], true) ? $this->pick($otherCrafts) : null, 'location' => $this->pick($areas), 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    /* ------------------------------------------------------------------ content */

    private function content(): void
    {
        $board = [['م. عبد الرحمن كاشف', 'رئيس مجلس الإدارة', '01001010101'], ['أ. سعاد إدريس', 'نائب الرئيس', '01112121212'], ['أ. منى عادل', 'أمين الصندوق', '01223232323'], ['أ. جمال بشير', 'السكرتير العام', '01554545454'], ['د. حسام شندي', 'عضو مجلس الإدارة', '01005656565'], ['أ. نجلاء فرج', 'عضو مجلس الإدارة', '01116767676']];
        foreach ($board as $b) {
            DB::table('border_members')->insert(['name' => $b[0], 'position' => $b[1], 'phone_number' => (int) $b[2], 'img' => 'demo-logo-arch.jpg', 'created_at' => '2024-01-10 10:00:00', 'updated_at' => '2024-01-10 10:00:00']);
        }
        $committees = [
            ['اللجنة الاجتماعية', 'زيارة المرضى، العزاء، ودعم الأسر', 'أ. سعاد إدريس', 'تنظيم العزاء بالمقر، زيارات المرضى، كفالة الأسر'],
            ['لجنة المقابر', 'متابعة المقابر والتُربية وأعمال الصيانة', 'أ. جمال بشير', 'تجديد مقابر 15 مايو، متابعة التُربية'],
            ['اللجنة الثقافية', 'الندوات، فصول محو الأمية، وتكريم الطلبة', 'د. حسام شندي', 'تكريم الأوائل، فصل محو الأمية، ليالي التراث النوبي'],
            ['لجنة الشباب والرياضة', 'دوري أدندان والرحلات', 'أ. نجلاء فرج', 'دوري كرة القدم، رحلة أسوان'],
            ['اللجنة المالية', 'الميزانية والتحصيل والتقارير', 'أ. منى عادل', 'ميزانية 2027، مراجعة الخزنة الشهرية'],
        ];
        foreach ($committees as $c) {
            DB::table('association_committes')->insert(['name' => $c[0], 'description' => $c[1], 'boss' => $c[2], 'tasks' => $c[3], 'created_at' => '2024-01-10 10:00:00', 'updated_at' => '2024-01-10 10:00:00']);
        }

        $body = 'كرّمت الجمعية أوائل الشهادات من أبناء أدندان في حفل بالمقر يوم الجمعة، بحضور أعضاء مجلس الإدارة وأولياء الأمور، ووزعت شهادات تقدير وجوائز رمزية على الطلبة المتفوقين. وأكد رئيس الجمعية أن التعليم هو أهم استثمار في مستقبل أبناء النوبة.';
        $news = [
            ['تكريم أوائل الطلبة من أبناء أدندان', 'أخبار ثقافية', '2026-11-19', 'drummers'], ['موعد الجمعية العمومية السنوية', 'أخبار عامة', '2026-11-15', 'board'],
            ['البقاء لله: وفاة سيد عبد الحليم نور', 'عزاء', '2026-11-12', 'cemeteries'], ['زفاف أحمد عثمان كاشف الخميس القادم', 'أفراح', '2026-11-10', 'weddings'],
            ['دوري أدندان لكرة القدم: الدور قبل النهائي', 'أخبار رياضية', '2026-11-06', 'map-3d'], ['افتتاح فصل محو الأمية بالمقر', 'أخبار ثقافية', '2026-10-30', 'news'],
            ['ليالي التراث النوبي في المقر', 'أخبار ثقافية', '2026-10-18', 'nubian-house'], ['حملة تطوير المقر: اتجمع 41,200 جنيه', 'أخبار عامة', '2026-10-05', 'door-scene'],
            ['رحلة الأعضاء إلى أسوان', 'أخبار عامة', '2026-09-20', 'nile-landscape'], ['كسوة الشتاء لأسر أدندان', 'أخبار عامة', '2026-09-02', 'hands'],
            ['دليل الحرفيين في التطبيق', 'أخبار عامة', '2026-08-14', 'craftsmen'], ['بدء تحصيل اشتراك سنة 2027', 'أخبار عامة', '2026-07-01', 'subscriptions'],
        ];
        $commenters = array_slice(array_keys($this->members), 0, 60);
        $comments = ['ربنا يبارك', 'ألف مبروك', 'البقاء لله وإنا إليه راجعون', 'مجهود عظيم من الجمعية', 'إمتى الموعد بالظبط؟', 'شكراً لكل القائمين على الجمعية', 'ربنا يرحمه ويصبر أهله', 'تحيا أدندان'];
        foreach ($news as $i => $n) {
            $at = $n[2].' 12:00:00';
            $newsId = DB::table('news')->insertGetId(['title' => $n[0], 'slug' => uniqid(), 'shortlink' => null, 'description' => $body, 'category' => $n[1], 'img' => "demo-$n[3].jpg", 'thumbnail' => "demo-$n[3].jpg", 'posted_by' => 'سارة محمود', 'created_at' => $at, 'updated_at' => $at]);
            if ($i === 0) {
                foreach (['drummers', 'weddings', 'nubian-house', 'door-scene', 'thumb-domes', 'village-strip'] as $thumb) {
                    DB::table('news_thumbnails')->insert(['thumbnail' => "demo-$thumb.jpg", 'news_id' => $newsId, 'created_at' => $at, 'updated_at' => $at]);
                }
                foreach (['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'https://www.youtube.com/watch?v=9bZkp7q19f0'] as $url) {
                    DB::table('news_videos')->insert(['url' => $url, 'news_id' => $newsId, 'created_at' => $at, 'updated_at' => $at]);
                }
            }
            for ($k = 0, $count = $i < 5 ? $this->r(4, 9) : $this->r(0, 3); $k < $count; $k++) {
                $memberId = $this->pick($commenters);
                $cat = CarbonImmutable::parse($at)->addHours($this->r(1, 60))->toDateTimeString();
                DB::table('news_comments')->insert(['author' => $this->members[$memberId]['name'], 'content' => $n[1] === 'عزاء' ? 'ربنا يرحمه ويصبر أهله' : ($n[1] === 'أفراح' ? 'ألف مبروك' : $this->pick($comments)), 'news_id' => $newsId, 'subscriber_id' => $this->rowIds[$memberId], 'created_at' => $cat, 'updated_at' => $cat]);
            }
        }

        foreach ([['2026-11-12 08:15:00', 'توفي سيد عبد الحليم نور بالمنزل بأدندان و صلاة الجنازة عقب صلاة العصر بـمسجد النور والدفن بالقطامية وعزاء الرجال السبت والأحد بالمقر'], ['2026-11-02 09:40:00', 'توفيت الحاجة آمنة حسن كرار بمستشفى عين شمس و صلاة الجنازة عقب صلاة الظهر والدفن بمايو وعزاء الرجال الثلاثاء بالمقر'], ['2026-10-19 07:55:00', 'توفي عوض محمود داود بالمنزل بشبرا و صلاة الجنازة عقب صلاة العصر والدفن بالغفير ولا يوجد عزاء للرجال ولا للسيدات']] as [$at, $msg]) {
            DB::table('sms_msgs')->insert(['sent_status' => 'sent', 'message' => $msg, 'created_at' => $at, 'updated_at' => $at]);
        }
    }

    /* ------------------------------------------------------------------ staff */

    private function staffAndRoles(): void
    {
        $roles = [
            'أمين الصندوق' => ['لوحة التحكم', 'المشتركين', 'الاشتراكات', 'الإشتراك السنوي', 'المديونيات', 'مديونية الاشتراك', 'مديونية التبرعات', 'التبرعات', 'المتبرعين', 'التبرعات الخارجية', 'النثريات', 'التقارير'],
            'موظف استقبال' => ['لوحة التحكم', 'المشتركين', 'مشترك جديد', 'الاشتراكات', 'التبرعات', 'الرسائل'],
            'محرر أخبار' => ['لوحة التحكم', 'الاخبار', 'الافراح', 'مجلس الادارة', 'اللجان'],
            'مسؤول الدليل' => ['لوحة التحكم', 'الحرفيين', 'المقابر', 'الافراح'],
        ];
        $existing = Permission::pluck('name')->all();
        foreach ($roles as $name => $perms) {
            Role::findOrCreate($name, 'web')->syncPermissions(array_values(array_intersect($perms, $existing)));
        }

        $staff = [['أ. منى عادل', 'mona@adendan.test', 'أمين الصندوق', 'subscriptions'], ['خالد عثمان', 'khaled@adendan.test', 'موظف استقبال', 'subscriptions'], ['هبة صالح', 'heba@adendan.test', 'موظف استقبال', 'subscriptions'], ['ياسين داود', 'yassin@adendan.test', 'موظف استقبال', 'subscriptions'], ['سارة محمود', 'sara@adendan.test', 'محرر أخبار', 'media'], ['رامي نور', 'rami@adendan.test', 'محرر أخبار', 'media'], ['عصام كرار', 'essam@adendan.test', 'مسؤول الدليل', 'media']];
        $userModel = config('auth.providers.users.model');
        foreach ($staff as [$name, $email, $role, $legacy]) {
            if ($userModel::where('email', $email)->exists()) {
                continue;
            }
            // demo staff cannot sign in: the password is random and never shown
            $user = $userModel::forceCreate(['name' => $name, 'email' => $email, 'password' => Hash::make(Str::random(40)), 'role' => $legacy, 'created_at' => '2024-01-10 10:00:00', 'updated_at' => '2024-01-10 10:00:00']);
            $user->assignRole($role);
        }
    }

    /* ------------------------------------------------------------------ requests, OCR, activity */

    private function requestsOcrAndActivity(): void
    {
        $req = [[1203, 'address', 'نصر النوبة، أسوان', '2026-11-20'], [1342, 'job_title', 'مدرسة', '2026-11-20'], [1402, 'mobile_no', '01277788899', '2026-11-19'], [1150, 'address', 'أسوان', '2026-11-18'], [1290, 'job_title', 'طبيبة', '2026-11-17'], [1056, 'birth_date', '1965-02-28', '2026-11-15'], [1187, 'address', 'المطرية، القاهرة', '2026-11-14']];
        foreach ($req as [$id, $field, $value, $date]) {
            $at = $this->at($date);
            DB::table('user_update_stagings')->insert(['member_id' => $id, 'name' => $this->members[$id]['name'], $field => $value, 'created_at' => $at, 'updated_at' => $at]);
        }

        $ocrMembers = array_slice(array_keys(array_filter($this->members, fn ($m) => $m['status'] === 1)), 20, 31);
        foreach ($ocrMembers as $i => $id) {
            $m = $this->members[$id];
            $address = $i % 2 === 0 ? $this->pick(['مصر الجديدة، القاهرة', 'المطرية، القاهرة', 'أسوان', 'الهرم، الجيزة']) : (string) $m['address'];
            $name = $i % 3 === 1 ? str_replace(['ي ', 'ة '], ['ى ', 'ه '], $m['name'].' ') : $m['name'];
            DB::table('ocr_data')->insert(['name' => trim($name), 'address' => $address ?: 'القاهرة', 'nid' => (int) $m['ssn'], 'birth_date' => $i % 5 === 4 ? CarbonImmutable::parse($m['birth'])->addDays(10)->toDateString() : $m['birth'], 'mobile' => null, 'created_at' => '2026-11-01 10:00:00', 'updated_at' => '2026-11-01 10:00:00']);
        }

        $users = DB::table('users')->pluck('id', 'name');
        $log = [
            ['سجل دفعة إشتراك 2027 بمبلغ 300 ج.م للعضو 1187', 'خالد عثمان', '2026-11-21 10:42:00', 'created'], ['حوّل مبلغ من الخزنة إلى البنك', 'أ. منى عادل', '2026-11-21 13:05:00', 'created'],
            ['سجل فاتورة صيانة سماعات القاعة 2,750 ج.م', 'أ. منى عادل', '2026-11-21 12:30:00', 'created'], ['عدّل رقم المحمول للعضو 1342', 'خالد عثمان', '2026-11-21 09:40:00', 'updated'],
            ['أضاف مشترك جديد: مصطفى عبد الله عوض (1811)', 'خالد عثمان', '2026-11-21 10:05:00', 'created'], ['وافق على طلب تحديث بيانات العضو 1203', 'هبة صالح', '2026-11-20 16:12:00', 'updated'],
            ['نشر خبر: تكريم أوائل الطلبة من أبناء أدندان', 'سارة محمود', '2026-11-19 12:00:00', 'created'], ['أرسل رسالة عزاء لكل المشتركين', 'خالد عثمان', '2026-11-12 08:15:00', 'created'],
            ['حذف مشترك رسائل (رقم غير صحيح)', 'ياسين داود', '2026-11-11 14:22:00', 'deleted'], ['أضاف فرح: أحمد عثمان كاشف', 'سارة محمود', '2026-11-10 11:05:00', 'created'],
            ['عدّل بيانات المقبرة: مقابر القطامية', 'عصام كرار', '2026-11-08 13:30:00', 'updated'], ['أضاف حرفي: عادل بشير (فني تكييفات)', 'عصام كرار', '2026-11-07 10:10:00', 'created'],
        ];
        foreach ($log as [$desc, $by, $at, $event]) {
            DB::table('activity_log')->insert(['log_name' => 'default', 'description' => $desc, 'subject_type' => null, 'subject_id' => null, 'event' => $event, 'causer_type' => config('auth.providers.users.model'), 'causer_id' => $users[$by] ?? null, 'properties' => json_encode($event === 'updated' ? ['old' => ['الوصف' => 'قبل التعديل'], 'attributes' => ['الوصف' => $desc]] : ['attributes' => ['الوصف' => $desc, 'بواسطة' => $by]], JSON_UNESCAPED_UNICODE), 'batch_uuid' => null, 'created_at' => $at, 'updated_at' => $at]);
        }
    }
}
