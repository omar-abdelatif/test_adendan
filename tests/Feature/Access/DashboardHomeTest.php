<?php

namespace Tests\Feature\Access;

use App\Models\CostYears;
use App\Models\Due;
use App\Models\PaymentTransaction;
use App\Models\Subscribers;
use App\Models\TotalBank;
use App\Models\TotalSafe;
use App\Models\UserUpdateStaging;
use App\Modules\Access\Application\DashboardOverview;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\ActsAsStaff;
use Tests\TestCase;

class DashboardHomeTest extends TestCase
{
    use ActsAsStaff, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-21 10:00:00'); // subscription year 2027
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_finance_staff_see_balances_collection_and_latest_movements(): void
    {
        $this->actingAsStaff('التقارير', 'المشتركين');
        $this->seedFinances();

        $this->get('/admin/dashboard/main')
            ->assertOk()
            ->assertSee('48,320')
            ->assertSee('215,000')
            ->assertSee('تحصيل اشتراك 2027')
            ->assertSee('50%')
            ->assertSee('1 من 2 عضو سددوا بالكامل')
            ->assertSee('350')
            ->assertSee('على 2 عضو')
            ->assertSee('فاطمة حسن إدريس')
            ->assertSee('−3,200', false)
            ->assertSee('طلبات تحديث البيانات')
            ->assertSee('منى عوض');
    }

    public function test_staff_without_report_access_do_not_see_money(): void
    {
        $this->actingAsStaff('المشتركين');
        $this->seedFinances();

        $this->get('/admin/dashboard/main')
            ->assertOk()
            ->assertDontSee('48,320')
            ->assertDontSee('215,000')
            ->assertDontSee('فاطمة حسن إدريس')
            ->assertSee('أعضاء مفعّلين')
            ->assertSee('منى عوض');
    }

    public function test_monthly_income_follows_the_july_to_june_year_and_ignores_withdrawals_and_transfers(): void
    {
        PaymentTransaction::factory()->create(['amount' => 500, 'payment_date' => '2026-09-14']);
        PaymentTransaction::factory()->create(['amount' => 360, 'payment_date' => '2026-11-02']);
        PaymentTransaction::factory()->create(['amount' => 40, 'payment_date' => '2026-11-20']);
        PaymentTransaction::factory()->create(['amount' => 999, 'payment_date' => '2026-06-30']); // last year
        PaymentTransaction::factory()->create(['amount' => 3200, 'payment_date' => '2026-11-03', 'transaction_cat' => 'سحب']);
        // safe to bank: the bank side is stored as a deposit, but it is not new money
        PaymentTransaction::factory()->create(['amount' => 20000, 'payment_date' => '2026-11-04', 'transaction_type' => 'تحويلات', 'account' => 'bank']);

        $income = app(DashboardOverview::class)->monthlyIncome(CarbonImmutable::parse('2026-11-21'));

        $this->assertSame(2027, $income['year']);
        $this->assertCount(12, $income['months']);
        $this->assertSame(['يوليو', 0], [$income['months'][0]['name'], $income['months'][0]['amount']]);
        $this->assertSame(500, $income['months'][2]['amount']);   // September
        $this->assertSame(400, $income['months'][4]['amount']);   // November
        $this->assertTrue($income['months'][4]['current']);
        $this->assertTrue($income['months'][5]['future']);
        $this->assertFalse($income['months'][4]['future']);
        $this->assertSame(500, $income['max']);
    }

    private function seedFinances(): void
    {
        TotalSafe::create(['amount' => 48320]);
        TotalBank::create(['amount' => 215000]);
        CostYears::factory()->create(['year' => 2027, 'cost' => 360]);

        $fatma = Subscribers::factory()->create(['name' => 'فاطمة حسن إدريس', 'status' => 1]);
        $other = Subscribers::factory()->create(['status' => 1]);

        Due::factory()->forMember($fatma)->amount(360, 360)->state(['item' => '2027'])->create();
        Due::factory()->forMember($other)->amount(360)->state(['item' => '2027'])->create();
        Due::factory()->forMember($fatma)->amount(200)->state(['item' => '2026'])->create();
        Due::factory()->forMember($other)->amount(150)->state(['item' => '2026'])->create();

        PaymentTransaction::factory()->create(['member_id' => $fatma->member_id, 'amount' => 360, 'item' => '2027']);
        PaymentTransaction::factory()->create(['item' => 'سرادق عزاء', 'amount' => 3200, 'transaction_cat' => 'سحب', 'transaction_type' => 'نثريات']);

        UserUpdateStaging::create(['name' => 'منى عوض', 'member_id' => 512]);
    }
}
