<?php

namespace Database\Seeders;

use App\Models\Donators;
use App\Models\FundDonation;
use App\Models\SponsorshipCase;
use App\Modules\Finance\Application\Actions\RecordFundDonation;
use App\Modules\Finance\Application\Actions\TransferFunds;
use App\Modules\Finance\Domain\Enums\Account;
use App\Modules\Finance\Domain\Enums\Fund;
use App\Modules\Shared\Domain\Money;
use App\Modules\Sponsorship\Application\Actions\ManageCases;
use App\Modules\Sponsorship\Domain\Enums\CaseDocument;
use Carbon\CarbonImmutable;
use Database\Seeders\Concerns\GuardsDemoData;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Local demo data for the sponsorship screens and the two separate funds:
 * cases (some with their papers), donations to the sponsorship and new-tombs
 * funds, and one transfer to the sponsorship bank. Everything goes through the
 * real actions, so the ledger and the balances agree.
 * Refuses in production and when cases already exist.
 *
 *   php artisan db:seed --class=SponsorshipDemoSeeder
 */
class SponsorshipDemoSeeder extends Seeder
{
    use GuardsDemoData;

    /** [name, age, governorate, marital, health, sons, daughters, monthly, pension, other, type, period] */
    private const CASES = [
        ['فاطمة إدريس عوض', 48, 'أسوان', 'widowed', 'ضغط وسكر', 2, 3, 0, 1600, 500, 'cash', 'monthly'],
        ['سعاد محمود كرار', 61, 'القاهرة', 'widowed', 'سليمة', 0, 1, 0, 900, 0, 'in_kind', 'seasonal'],
        ['حليمة سيد نور', 39, 'الجيزة', 'divorced', 'سليمة', 3, 1, 1500, 0, 0, 'cash', 'monthly'],
        ['آمنة طه بشير', 55, 'أسوان', 'widowed', 'مريضة قلب', 1, 2, 0, 1100, 0, 'cash', 'seasonal'],
        ['نفيسة عثمان دهب', 70, 'القاهرة', 'widowed', 'كبيرة في السن', 0, 0, 0, 700, 0, 'in_kind', 'monthly'],
        ['خديجة صالح شندي', 44, 'نصر النوبة', 'married', 'سليمة', 4, 2, 1800, 0, 0, 'cash', 'monthly'],
        ['زينب عوض فرج', 52, 'القاهرة', 'widowed', 'سكر', 1, 1, 0, 950, 300, 'in_kind', 'seasonal'],
        ['هانم حسن جعفر', 36, 'الجيزة', 'divorced', 'سليمة', 2, 0, 1000, 0, 0, 'cash', 'monthly'],
        ['محمد عبده إدريس', 62, 'أسوان', 'married', 'عجز', 3, 2, 0, 1200, 400, 'cash', 'monthly'],
        ['رقية محمد سليم', 67, 'أسوان', 'widowed', 'ضغط', 0, 2, 0, 850, 0, 'in_kind', 'seasonal'],
        ['بخيتة علي كاشف', 58, 'القاهرة', 'widowed', 'سليمة', 2, 2, 0, 1400, 0, 'cash', 'seasonal'],
        ['عائشة جمال نور', 29, 'القاهرة', 'widowed', 'سليمة', 2, 1, 1200, 0, 0, 'in_kind', 'monthly'],
        ['مريم عثمان حامد', 33, 'الجيزة', 'divorced', 'سليمة', 1, 2, 900, 0, 200, 'cash', 'monthly'],
        ['صفية إبراهيم داود', 74, 'نصر النوبة', 'widowed', 'كبيرة في السن', 0, 0, 0, 600, 0, 'in_kind', 'monthly'],
        ['جميلة حسن سنوسي', 46, 'أسوان', 'widowed', 'غضروف', 3, 3, 0, 1300, 0, 'cash', 'monthly'],
        ['عبد الرحيم كمال شندي', 69, 'القاهرة', 'married', 'مرض مزمن', 0, 1, 0, 1500, 0, 'cash', 'seasonal'],
        ['نعمات طه عوض', 57, 'الإسكندرية', 'widowed', 'سليمة', 1, 0, 0, 800, 0, 'in_kind', 'seasonal'],
        ['سكينة محمود جعفر', 41, 'الجيزة', 'married', 'سليمة', 2, 3, 1700, 0, 0, 'cash', 'monthly'],
        ['حواء إدريس كرار', 50, 'أسوان', 'divorced', 'سكر', 2, 1, 0, 0, 1100, 'in_kind', 'monthly'],
        ['رحمة صالح بشير', 26, 'القاهرة', 'single', 'سليمة', 0, 0, 0, 0, 0, 'cash', 'monthly'],
    ];

    /** sponsorship categories, in the order the demo donations use them */
    private const CATEGORIES = ['صدقات', 'زكاة مال', 'كفالة يتيم', 'صدقات', 'زكاة فطر', 'أخرى'];

    public function run(ManageCases $cases, RecordFundDonation $donations, TransferFunds $transfer): void
    {
        $this->ensureDemoDataAllowed();
        if (SponsorshipCase::exists() || FundDonation::exists()) {
            throw new RuntimeException('There are cases or fund donations already; the sponsorship demo data is not added twice.');
        }

        foreach (self::CASES as $i => [$name, $age, $gov, $marital, $health, $sons, $daughters, $monthly, $pension, $other, $type, $period]) {
            // Most files are complete; every fourth case still lacks its papers, every fifth its ID number.
            $uploads = $i % 4 === 3 ? [] : [[CaseDocument::IdCard, $this->png("id-{$i}.png")], [CaseDocument::Research, $this->png("research-{$i}.png")]];
            $cases->create([
                'name' => $name,
                'national_id' => $i % 5 === 4 ? null : sprintf('2%02d%02d%02d%07d', 99 - $age % 60, 1 + $i % 12, 1 + $i, 1300000 + $i * 7919),
                'phone' => '010'.sprintf('%08d', 22430000 + $i * 1373),
                'age' => $age,
                'address' => ['شارع النيل', 'عزبة النخل', 'حي السلام', 'كفر الجبل'][$i % 4],
                'governorate' => $gov,
                'marital_status' => $marital,
                'health_status' => $health,
                'sons' => $sons,
                'daughters' => $daughters,
                'monthly_income' => $monthly,
                'pension_income' => $pension,
                'other_income' => $other,
                'benefit_type' => $type,
                'benefit_period' => $period,
            ], $uploads);
        }

        $donors = Donators::query()->orderBy('id')->get();
        if ($donors->count() < 4) {
            foreach (['حسن عبد الله صالح', 'نادية سيد كرار', 'عمر إدريس شندي', 'ليلى محمود نور'] as $n => $name) {
                $donors->push(Donators::create(['name' => $name, 'mobile_number' => '0112'.sprintf('%07d', 3300000 + $n * 911), 'donator_type' => $n % 2 ? 'موسمي' : 'منتظم']));
            }
        }

        $today = CarbonImmutable::parse('2026-11-23');
        foreach (range(0, 27) as $n) {
            $category = self::CATEGORIES[$n % count(self::CATEGORIES)];
            $donations->record($donors[$n % $donors->count()], Fund::Sponsorship, [
                'amount' => [500, 1000, 250, 2000, 300, 750, 1500][$n % 7],
                'account' => $n % 6 === 5 ? Account::Bank->value : Account::Safe->value,
                'invoice_no' => (string) (4100 + $n),
                'category' => $category,
                'category_other' => $category === 'أخرى' ? 'علاج حالة' : null,
                'months' => $n % 3 === 0 ? [] : [FundDonation::MONTHS[(10 - intdiv($n, 7)) % 12]],
                'donated_on' => $today->subDays($n * 4 + 1)->toDateString(),
            ]);
        }
        foreach (range(0, 9) as $n) {
            $donations->record($donors[($n + 2) % $donors->count()], Fund::NewTombs, [
                'amount' => [5000, 2000, 10000, 1000, 3000][$n % 5],
                'account' => $n % 4 === 3 ? Account::Bank->value : Account::Safe->value,
                'invoice_no' => (string) (900 + $n),
                'donated_on' => $today->subDays($n * 9 + 2)->toDateString(),
            ]);
        }

        $transfer->handle(Account::Safe, Account::Bank, Money::pounds(5000), $this->png('deposit.png'), $today->subDays(5), Fund::Sponsorship);
    }

    /** A one-pixel image standing in for a scanned paper. */
    private function png(string $name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'demo');
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mN8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg=='));

        return new UploadedFile($path, $name, 'image/png', null, true);
    }
}
