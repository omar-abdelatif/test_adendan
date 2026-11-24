<?php

namespace Tests\Feature\Sponsorship;

use App\Models\SponsorshipCase;
use App\Models\SponsorshipCaseFile;
use App\Modules\Sponsorship\Domain\Enums\CaseDocument;
use App\Modules\Sponsorship\Domain\Enums\MaritalStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Concerns\ActsAsStaff;
use Tests\TestCase;

class CasesTest extends TestCase
{
    use ActsAsStaff, RefreshDatabase;

    private function caseData(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'فاطمة إدريس عوض', 'national_id' => '27801011234567', 'phone' => '1012345678', 'age' => 48,
            'governorate' => 'أسوان', 'marital_status' => 'widowed', 'health_status' => 'ضغط وسكر', 'sons' => 2, 'daughters' => 3,
            'monthly_income' => 0, 'pension_income' => 1600, 'other_income' => 500, 'benefit_type' => 'cash', 'benefit_period' => 'monthly',
        ];
    }

    private function makeCase(array $overrides = []): SponsorshipCase
    {
        return SponsorshipCase::create($this->caseData($overrides + ['phone' => '01012345678']));
    }

    public function test_a_case_is_added_with_its_papers_kept_private(): void
    {
        Storage::fake('local');
        $this->actingAsStaff('الكفالة');

        $this->post('/admin/sponsorship/cases', $this->caseData([
            'id_card' => UploadedFile::fake()->create('card.pdf', 80, 'application/pdf'),
            'photos' => [UploadedFile::fake()->image('home.jpg')],
        ]))->assertSessionHasNoErrors()->assertSessionHas('alert-type', 'success')->assertSessionHas('open_case');

        $case = SponsorshipCase::sole();
        $this->assertSame('01012345678', $case->phone);
        $this->assertSame(MaritalStatus::Widowed, $case->marital_status);
        $this->assertSame(2100, $case->totalIncome());
        $this->assertSame(['بحث الحالة'], $case->missing());

        $files = $case->files;
        $this->assertCount(2, $files);
        foreach ($files as $file) {
            Storage::disk('local')->assertExists($file->path);
            $this->assertStringStartsWith("sponsorship/cases/{$case->id}/", $file->path);
        }

        $this->get('/admin/sponsorship/files/'.$files->first()->id)->assertOk();
        $this->actingAsStaff('الاخبار');
        $this->get('/admin/sponsorship/files/'.$files->first()->id)->assertForbidden();
    }

    public function test_papers_are_added_and_removed_from_the_case_file(): void
    {
        Storage::fake('local');
        $this->actingAsStaff('الكفالة');
        $case = $this->makeCase();

        $this->post("/admin/sponsorship/cases/{$case->id}/files", ['kind' => 'research', 'files' => [UploadedFile::fake()->image('research.png')]])
            ->assertSessionHas('open_case', $case->id);
        $file = SponsorshipCaseFile::sole();
        $this->assertSame(CaseDocument::Research, $file->kind);

        $this->delete("/admin/sponsorship/files/{$file->id}")->assertSessionHas('alert-type', 'success');
        Storage::disk('local')->assertMissing($file->path);
        $this->assertSame(0, SponsorshipCaseFile::count());
    }

    public function test_a_case_is_changed_and_removed_with_its_papers(): void
    {
        Storage::fake('local');
        $this->actingAsStaff('الكفالة');
        $this->post('/admin/sponsorship/cases', $this->caseData(['research' => UploadedFile::fake()->image('r.png')]));
        $case = SponsorshipCase::sole();
        $path = $case->files->first()->path;

        $this->put("/admin/sponsorship/cases/{$case->id}", $this->caseData(['benefit_type' => 'in_kind', 'sons' => 3]))
            ->assertSessionHasNoErrors()->assertSessionHas('alert-type', 'success');
        $this->assertSame('in_kind', $case->fresh()->benefit_type->value);
        $this->assertSame(3, $case->fresh()->sons);

        $this->delete("/admin/sponsorship/cases/{$case->id}")->assertSessionHas('alert-type', 'success');
        $this->assertSame(0, SponsorshipCase::count());
        Storage::disk('local')->assertMissing($path);
    }

    public function test_the_case_rules(): void
    {
        $this->actingAsStaff('الكفالة');
        $this->makeCase();

        $this->post('/admin/sponsorship/cases', $this->caseData(['name' => 'تانية']))->assertSessionHasErrors('national_id');
        $this->post('/admin/sponsorship/cases', $this->caseData(['national_id' => '123']))->assertSessionHasErrors('national_id');
        $this->post('/admin/sponsorship/cases', $this->caseData(['national_id' => null, 'phone' => '12345']))->assertSessionHasErrors('phone');
        $this->post('/admin/sponsorship/cases', $this->caseData(['national_id' => null, 'benefit_type' => 'gold']))->assertSessionHasErrors('benefit_type');
        $this->assertSame(1, SponsorshipCase::count());
    }

    public function test_the_cards_are_filtered_searched_and_paged(): void
    {
        $this->actingAsStaff('الكفالة');
        $this->makeCase(['name' => 'حالة نقدية', 'national_id' => '27801011234561', 'phone' => '01011112222']);
        $this->makeCase(['name' => 'حالة عينية', 'national_id' => '27801011234562', 'phone' => '01033334444', 'benefit_type' => 'in_kind', 'governorate' => 'القاهرة']);

        $this->get('/admin/sponsorship/cases?type=cash')->assertOk()->assertSee('حالة نقدية')->assertDontSee('حالة عينية');
        $this->get('/admin/sponsorship/cases?gov=القاهرة')->assertOk()->assertSee('حالة عينية')->assertDontSee('حالة نقدية');
        // A phone typed without its leading zero still finds the case.
        $this->get('/admin/sponsorship/cases?q=1033334444')->assertOk()->assertSee('حالة عينية')->assertDontSee('حالة نقدية');
        $this->get('/admin/sponsorship/cases?q=مش موجودة')->assertOk()->assertSee('مفيش حالات بالفلاتر دي');

        foreach (range(3, 14) as $i) {
            $this->makeCase(['name' => "حالة {$i}", 'national_id' => sprintf('278010112345%02d', $i)]);
        }
        $this->get('/admin/sponsorship/cases')->assertOk()->assertSee('هات 2 كمان');
        $this->get('/admin/sponsorship/cases?page=2')->assertOk()->assertSee('عرض <span data-case-shown>14</span> من 14', false);
    }

    public function test_the_card_says_what_the_file_is_missing(): void
    {
        $this->actingAsStaff('الكفالة');
        $case = $this->makeCase(['national_id' => null]);

        $this->get('/admin/sponsorship/cases')->assertOk()->assertSee('ناقص: صورة البطاقة');
        $this->get("/admin/sponsorship/cases/{$case->id}")->assertOk()->assertSee('ناقص من الملف: صورة البطاقة، بحث الحالة، الرقم القومي');
    }

    public function test_cases_are_imported_from_the_old_system_sheet(): void
    {
        $this->actingAsStaff('الكفالة');
        $this->makeCase(['national_id' => '27801011234500']);

        $sheet = new Spreadsheet;
        $sheet->getActiveSheet()->fromArray([
            ['fullname', 'ssn', 'phone_number', 'age', 'address', 'gov', 'income_type', 'benefit_type', 'benefit_duration', 'marital_status', 'health_status', 'monthly_income', 'retire_income', 'another_source', 'total_income', 'sons', 'daughters'],
            ['سعاد محمود كرار', 29001011234567.0, 1001234567, 61, 'عزبة النخل', 'القاهرة', 'معاش', 'عينية', 'موسمية', 'widow', 'سليمة', 0, 900, 0, 900, 0, 1],
            ['متسجلة قبل كده', '27801011234500', '01000000000', 50, '', 'أسوان', 'بدون', 'نقدية', 'شهرية', 'أرمل/ة', '', 0, 0, 0, 0, 1, 1],
            ['من غير استفادة', '27801011234599', '01000000001', 40, '', 'أسوان', 'بدون', '', '', '', '', 0, 0, 0, 0, 0, 0],
        ]);
        $path = tempnam(sys_get_temp_dir(), 'cases').'.xlsx';
        (new Xlsx($sheet))->save($path);

        $this->post('/admin/sponsorship/cases/import', ['sheet' => new UploadedFile($path, 'cases.xlsx', null, null, true)])
            ->assertSessionHas('alert-type', 'warning');

        $case = SponsorshipCase::where('name', 'سعاد محمود كرار')->sole();
        $this->assertSame('29001011234567', $case->national_id);
        $this->assertSame('01001234567', $case->phone);
        $this->assertSame(MaritalStatus::Widowed, $case->marital_status);
        $this->assertSame('in_kind', $case->benefit_type->value);
        $this->assertSame('seasonal', $case->benefit_period->value);
        $this->assertSame(900, $case->pension_income);
        $this->assertSame(2, SponsorshipCase::count());
    }

    public function test_the_pages_render_and_need_the_sponsorship_permission(): void
    {
        $this->actingAsStaff('الكفالة');
        $case = $this->makeCase();

        $this->get('/admin/sponsorship/cases')->assertOk()->assertSee($case->name);
        $this->get("/admin/sponsorship/cases/{$case->id}")->assertOk()->assertSee('الدخل في الشهر');
        $this->get('/admin/sponsorship/reports')->assertOk()->assertSee('نقدية · شهرية');
        $this->get('/admin/sponsorship/reports/export')->assertOk()->assertDownload();

        $this->actingAsStaff('المقابر');
        $this->get('/admin/sponsorship/cases')->assertForbidden();
        $this->get("/admin/sponsorship/cases/{$case->id}")->assertForbidden();
        $this->post('/admin/sponsorship/cases', [])->assertForbidden();
        $this->get('/admin/sponsorship/reports')->assertForbidden();
    }
}
