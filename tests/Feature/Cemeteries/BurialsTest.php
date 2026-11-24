<?php

namespace Tests\Feature\Cemeteries;

use App\Models\Burial;
use App\Models\CemeteryChamber;
use App\Models\CemeteryRegion;
use App\Models\CemeteryTomb;
use App\Models\Tombs;
use App\Modules\Cemeteries\Application\Actions\ManageBurials;
use App\Modules\Cemeteries\Application\Actions\ManageTombs;
use App\Modules\Cemeteries\Application\Queries\CemeteryViews;
use App\Modules\Cemeteries\Domain\CemeteryException;
use App\Modules\Cemeteries\Infrastructure\Imports\PastBurialsImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class BurialsTest extends TestCase
{
    use RefreshDatabase;

    private CemeteryRegion $region;

    private CemeteryTomb $mixed;

    private CemeteryTomb $menLahd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->region = CemeteryRegion::create(['name' => 'القطامية', 'slug' => 'katameya']);
        $tombs = app(ManageTombs::class);
        $this->mixed = $tombs->create(['region_id' => $this->region->id, 'name' => 'مقابر القطامية', 'kind' => 'eyes', 'serves' => 'mixed'], 2);
        $this->menLahd = $tombs->create(['region_id' => $this->region->id, 'name' => 'مقبرة الرجال', 'kind' => 'lahd', 'serves' => 'men'], 2);
    }

    private function chamber(CemeteryTomb $tomb, int $number): CemeteryChamber
    {
        return $tomb->chambers()->where('number', $number)->sole();
    }

    private function bury(CemeteryTomb $tomb, int $chamber, string $gender = 'male', int $places = 1, string $name = 'متوفي'): Burial
    {
        return app(ManageBurials::class)->record([
            'region_id' => $tomb->region_id, 'tomb_id' => $tomb->id, 'chamber_id' => $this->chamber($tomb, $chamber)->id,
            'name' => $name, 'gender' => $gender, 'places' => $places, 'burial_date' => '2026-11-12',
        ]);
    }

    private function refused(callable $action, string $message): void
    {
        try {
            $action();
            $this->fail('Expected CemeteryException');
        } catch (CemeteryException $e) {
            $this->assertStringContainsString($message, $e->getMessage());
        }
    }

    public function test_tombs_get_numbered_chambers_of_the_right_size(): void
    {
        $this->assertSame([1, 2], $this->mixed->chambers->pluck('number')->all());
        $this->assertSame([6, 6], $this->mixed->chambers->pluck('capacity')->all());
        $this->assertSame([1, 1], $this->menLahd->chambers->pluck('capacity')->all());
    }

    public function test_a_burial_takes_its_places_and_a_full_chamber_refuses_more(): void
    {
        $this->bury($this->mixed, 1, places: 4);
        $this->bury($this->mixed, 1, places: 2);

        $this->refused(fn () => $this->bury($this->mixed, 1), 'عين 1 مليانة');
        $this->assertSame(2, Burial::count());
    }

    public function test_the_tombs_gender_is_enforced(): void
    {
        $this->refused(fn () => $this->bury($this->menLahd, 1, 'female'), 'للرجال بس');
    }

    public function test_a_mixed_chamber_keeps_one_gender_until_it_is_cleared(): void
    {
        $this->bury($this->mixed, 1, 'male');
        $this->refused(fn () => $this->bury($this->mixed, 1, 'female'), 'فيها رجال');
        $this->bury($this->mixed, 2, 'female'); // another chamber is fine

        app(ManageBurials::class)->clearChamber($this->chamber($this->mixed, 1));
        $this->bury($this->mixed, 1, 'female');
        $this->assertSame(1, $this->chamber($this->mixed, 1)->activeBurials()->count());
    }

    public function test_a_lahd_takes_one_burial(): void
    {
        $first = $this->bury($this->menLahd, 1, places: 3);

        $this->assertSame(1, $first->places);
        $this->refused(fn () => $this->bury($this->menLahd, 1), 'لحد 1 مليانة');
    }

    public function test_clearing_keeps_the_records_and_logs_it(): void
    {
        $this->bury($this->mixed, 1, name: 'سيد عبد الحليم نور');
        $this->bury($this->mixed, 1, name: 'حسن طه دهب');

        $this->assertSame(2, app(ManageBurials::class)->clearChamber($this->chamber($this->mixed, 1)));

        $this->assertSame(2, Burial::past()->count());
        $this->assertSame(0, Burial::inChambers()->count());
        $this->assertNotNull($this->chamber($this->mixed, 1)->last_cleared_at);
        $this->assertStringContainsString('تم تطهير عين 1', Activity::latest('id')->first()->description);
        $this->refused(fn () => app(ManageBurials::class)->clearChamber($this->chamber($this->mixed, 1)), 'فاضية أصلاً');
    }

    public function test_editing_a_burial_does_not_count_it_twice_and_a_move_is_checked(): void
    {
        $burial = $this->bury($this->mixed, 1, places: 6);
        $data = ['region_id' => $this->region->id, 'tomb_id' => $this->mixed->id, 'chamber_id' => $this->chamber($this->mixed, 1)->id, 'name' => 'اسم جديد', 'gender' => 'male', 'places' => 6];

        app(ManageBurials::class)->update($burial, $data);
        $this->assertSame('اسم جديد', $burial->fresh()->name);

        $this->bury($this->mixed, 2, 'female');
        $this->refused(fn () => app(ManageBurials::class)->update($burial, [...$data, 'chamber_id' => $this->chamber($this->mixed, 2)->id]), 'فيها سيدات');
    }

    public function test_a_tomb_with_burials_is_kept_and_its_used_chambers_cannot_go(): void
    {
        $this->bury($this->mixed, 2);
        $tombs = app(ManageTombs::class);

        $this->refused(fn () => $tombs->delete($this->mixed), 'فيها 1 دفنة');
        $this->refused(fn () => $tombs->update($this->mixed, ['name' => 'مقابر القطامية'], 1), 'عين 2 فيها دفنات');

        $tombs->update($this->mixed, ['name' => 'مقابر القطامية'], 4);
        $this->assertSame([1, 2, 3, 4], $this->mixed->chambers()->pluck('number')->all());

        $tombs->delete($this->menLahd);
        $this->assertNull($this->menLahd->fresh());
        $this->assertSame(0, CemeteryChamber::where('tomb_id', $this->menLahd->id)->count());
    }

    public function test_village_burials_need_no_chamber(): void
    {
        // The migration always adds the village.
        $village = CemeteryRegion::where('is_village', true)->sole();

        $burial = app(ManageBurials::class)->record(['region_id' => $village->id, 'name' => 'إدريس صالح', 'gender' => 'male', 'burial_date' => '2026-08-20']);

        $this->assertNull($burial->chamber_id);
        $this->assertSame(1, Burial::inVillage()->count());
        $this->refused(fn () => app(ManageBurials::class)->record(['region_id' => $this->region->id, 'tomb_id' => $this->mixed->id, 'name' => 'x', 'gender' => 'male']), 'لازم تختار العين');
    }

    public function test_the_overview_counts_places_for_men_and_women(): void
    {
        $this->bury($this->mixed, 1, 'male', 2);
        $this->bury($this->menLahd, 1);

        $card = app(CemeteryViews::class)->overview()['regions'][0];

        // men: chamber 1 (4 of 6 free) + chamber 2 (6) + lahd 2 (1 of 2 free)
        $this->assertSame(['free' => 11, 'total' => 14], $card['men']);
        // women: only the empty mixed chamber 2 can take a woman
        $this->assertSame(['free' => 6, 'total' => 6], $card['women']);
    }

    public function test_past_burials_come_in_from_a_sheet_once(): void
    {
        $rows = collect([
            collect(['name' => 'عثمان كاشف', 'death_date' => '01-02-2015', 'burial_date' => '02-02-2015', 'region' => 'القطامية', 'tomb' => 'مقابر القطامية', 'gender' => 'ذكر']),
            collect(['name' => 'مجهولة المنطقة', 'death_date' => null, 'burial_date' => null, 'region' => 'مكان تاني', 'tomb' => null]),
        ]);
        $import = new PastBurialsImport(app(ManageBurials::class));
        $import->collection($rows);
        (new PastBurialsImport(app(ManageBurials::class)))->collection($rows->take(1));

        $this->assertSame(1, $import->imported);
        $this->assertCount(1, $import->skipped);
        $this->assertSame(1, Burial::past()->count());
        $this->assertSame($this->mixed->id, Burial::sole()->tomb_id);
    }

    public function test_the_website_and_app_still_read_tombs_the_old_way(): void
    {
        $this->mixed->update(['guard_name' => 'عبد الصبور حامد', 'guard_phone' => '01117788990']);

        $json = $this->getJson('/api/all_tombs')->assertOk()->json();
        $tomb = collect($json)->firstWhere('title', 'مقابر القطامية');

        $this->assertSame(['id', 'location', 'created_at', 'updated_at', 'title', 'tomb_type', 'tomb_specialization', 'region', 'tomb_guard_name', 'tomb_guard_number'], array_keys($tomb));
        $this->assertSame('مختلط', $tomb['tomb_type']);
        $this->assertSame('عيون', $tomb['tomb_specialization']);
        $this->assertSame('القطامية', $tomb['region']);
        $this->assertSame(1117788990, $tomb['tomb_guard_number']);

        $this->expectException(\LogicException::class);
        Tombs::first()->forceFill(['location' => 'x'])->save();
    }
}
