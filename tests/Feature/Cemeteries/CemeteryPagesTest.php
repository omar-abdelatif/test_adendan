<?php

namespace Tests\Feature\Cemeteries;

use App\Models\Burial;
use App\Models\BurialFile;
use App\Models\CemeteryRegion;
use App\Models\Subscribers;
use App\Modules\Cemeteries\Application\Actions\ManageTombs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ActsAsStaff;
use Tests\TestCase;

class CemeteryPagesTest extends TestCase
{
    use ActsAsStaff, RefreshDatabase;

    private function tomb(string $serves = 'mixed')
    {
        $region = CemeteryRegion::create(['name' => 'القطامية', 'slug' => 'katameya']);

        return app(ManageTombs::class)->create(['region_id' => $region->id, 'name' => 'مقابر القطامية', 'kind' => 'eyes', 'serves' => $serves], 3);
    }

    public function test_the_pages_render(): void
    {
        $this->actingAsStaff('المقابر');
        $tomb = $this->tomb();

        // Each tomb is a row that opens it, with its own free places.
        $this->get('/admin/cemeteries')->assertOk()->assertSee('القطامية')
            ->assertSee('aria-label="ادخل مقابر القطامية"', false)->assertSee(route('cemeteries.tombs.show', $tomb))->assertSee('فاضي من');
        $this->get("/admin/cemeteries/tombs/{$tomb->id}")->assertOk()->assertSee('عين 3');
        foreach (['chambers', 'past', 'village'] as $tab) {
            $this->get("/admin/cemeteries/burials?tab={$tab}")->assertOk();
        }
        // The old directory address lands on the new overview.
        $this->get('/admin/tomb/tombs/all')->assertRedirect('/admin/cemeteries');
    }

    public function test_the_pages_need_the_cemeteries_permission(): void
    {
        $tomb = $this->tomb();
        $this->actingAsStaff('الاخبار');

        $this->get('/admin/cemeteries')->assertForbidden();
        $this->get("/admin/cemeteries/tombs/{$tomb->id}")->assertForbidden();
        $this->post('/admin/cemeteries/burials', [])->assertForbidden();
    }

    public function test_a_burial_is_recorded_with_its_files_kept_private(): void
    {
        Storage::fake('local');
        $this->actingAsStaff('المقابر');
        $tomb = $this->tomb();
        $chamber = $tomb->chambers()->where('number', 2)->sole();
        $member = Subscribers::factory()->create(['member_id' => 1012]);

        $this->post('/admin/cemeteries/burials', [
            'region_id' => $tomb->region_id, 'tomb_id' => $tomb->id, 'chamber_id' => $chamber->id,
            'name' => 'سيد عبد الحليم نور', 'gender' => 'male', 'places' => 1, 'member_id' => 1012,
            'death_date' => '2026-11-11', 'burial_date' => '2026-11-12',
            'certificate' => UploadedFile::fake()->create('certificate.pdf', 120, 'application/pdf'),
        ])->assertSessionHasNoErrors()->assertSessionHas('alert-type', 'success');

        $burial = Burial::sole();
        $this->assertSame($member->id, $burial->subscriber_id);
        $file = BurialFile::sole();
        Storage::disk('local')->assertExists($file->path);
        $this->assertStringStartsWith('cemeteries/burials/', $file->path);

        $this->get("/admin/cemeteries/files/{$file->id}")->assertOk();
        $this->actingAsStaff('الاخبار');
        $this->get("/admin/cemeteries/files/{$file->id}")->assertForbidden();
    }

    public function test_a_refused_burial_comes_back_with_the_reason(): void
    {
        $this->actingAsStaff('المقابر');
        $tomb = $this->tomb('men');
        $chamber = $tomb->chambers()->first();

        $this->post('/admin/cemeteries/burials', [
            'region_id' => $tomb->region_id, 'tomb_id' => $tomb->id, 'chamber_id' => $chamber->id,
            'name' => 'زينب محمد صالح', 'gender' => 'female', 'burial_date' => '2026-11-14',
        ])->assertSessionHas('alert-type', 'error')->assertSessionHas('message', 'مقابر القطامية للرجال بس.');

        $this->assertSame(0, Burial::count());
    }

    public function test_a_chamber_is_cleared_from_its_page(): void
    {
        $this->actingAsStaff('المقابر');
        $tomb = $this->tomb();
        $chamber = $tomb->chambers()->first();
        $this->post('/admin/cemeteries/burials', ['region_id' => $tomb->region_id, 'tomb_id' => $tomb->id, 'chamber_id' => $chamber->id, 'name' => 'حسن طه دهب', 'gender' => 'male', 'burial_date' => '2026-08-04']);

        $this->post("/admin/cemeteries/chambers/{$chamber->id}/clear")->assertSessionHas('alert-type', 'success');
        $this->assertSame(1, Burial::past()->count());

        $this->post("/admin/cemeteries/chambers/{$chamber->id}/clear")->assertSessionHas('alert-type', 'error');
    }

    public function test_a_tomb_is_added_and_changed_from_the_dashboard(): void
    {
        $this->actingAsStaff('المقابر');
        $region = CemeteryRegion::create(['name' => 'أكتوبر', 'slug' => 'october']);

        $this->post('/admin/cemeteries/tombs', [
            'region_id' => $region->id, 'name' => 'مقبرة أكتوبر الجديدة', 'kind' => 'lahd', 'serves' => 'men', 'chambers' => 20, 'guard_phone' => '01559900112',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $tomb = $region->tombs()->sole();
        $this->assertSame(20, $tomb->chambers()->count());

        $this->put("/admin/cemeteries/tombs/{$tomb->id}", [
            'region_id' => $region->id, 'name' => 'مقبرة أكتوبر الجديدة', 'kind' => 'lahd', 'serves' => 'men', 'chambers' => 22,
        ])->assertSessionHas('alert-type', 'success');
        $this->assertSame(22, $tomb->chambers()->count());
    }
}
