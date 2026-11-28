<?php

namespace Tests\Feature\Members;

use App\Models\OcrData;
use App\Models\SearchedData;
use App\Models\Subscribers;
use App\Models\UserUpdateStaging;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\ActsAsStaff;
use Tests\TestCase;

class ProfileChangesTest extends TestCase
{
    use ActsAsStaff, RefreshDatabase;

    public function test_staff_approve_a_website_request(): void
    {
        $this->actingAsStaff('التقارير');
        $member = Subscribers::factory()->create(['address' => null]);
        $request = SearchedData::create(['member_id' => $member->member_id, 'address' => 'العنوان الجديد']);

        $this->postJson('/admin/searched_data/approve', ['member_id' => $member->member_id, 'address' => 'العنوان الجديد'])
            ->assertOk()->assertJson(['status' => true, 'id' => $request->id]);

        $this->assertSame('العنوان الجديد', $member->fresh()->address);
        $this->assertSame(0, SearchedData::count());
    }

    public function test_ocr_scan_updates_the_member_with_the_same_national_id(): void
    {
        $this->actingAsStaff('التقارير');
        $member = Subscribers::factory()->create(['ssn' => 29001011234567, 'address' => 'قديم']);
        $scan = OcrData::create(['name' => 'اسم', 'nid' => '29001011234567', 'address' => 'من البطاقة', 'birth_date' => '1990-01-01', 'mobile' => '01099999999']);

        $this->post("/admin/reports/subscribers_data_ocr/approve/{$scan->id}")->assertSessionHas('alert-type', 'success');

        $this->assertSame('من البطاقة', $member->fresh()->address);
        $this->assertSame(0, OcrData::count());
    }

    public function test_ocr_scan_without_a_matching_member_is_kept(): void
    {
        $this->actingAsStaff('التقارير');
        $scan = OcrData::create(['name' => 'اسم', 'nid' => '29001011234567', 'address' => 'x', 'birth_date' => '1990-01-01', 'mobile' => '01099999999']);

        $this->post("/admin/reports/subscribers_data_ocr/approve/{$scan->id}")->assertSessionHas('alert-type', 'error');

        $this->assertSame(1, OcrData::count());
    }

    public function test_app_request_is_applied_only_when_one_is_waiting(): void
    {
        $this->actingAsStaff('المستخدمين');
        $member = Subscribers::factory()->create();
        $form = ['member_id' => $member->member_id, 'name' => 'الاسم المعدل', 'job_title' => 'مهندس'];

        $this->postJson('/admin/users/update_request', $form)->assertNotFound();

        UserUpdateStaging::create(['member_id' => $member->member_id, 'name' => 'الاسم المعدل']);
        $this->postJson('/admin/users/update_request', $form)->assertOk();

        $this->assertSame('الاسم المعدل', $member->fresh()->name);
        $this->assertSame('مهندس', $member->fresh()->job);
        $this->assertSame(0, UserUpdateStaging::count());
    }

    public function test_member_sends_update_from_the_app_and_sees_only_their_own_status(): void
    {
        $member = Subscribers::factory()->create();
        $other = Subscribers::factory()->create();
        UserUpdateStaging::create(['member_id' => $other->member_id, 'name' => 'x']);
        Sanctum::actingAs($member);

        // Asking about another member's id still answers for the logged-in member.
        $this->getJson("/api/user/update_is_pending/{$other->member_id}")->assertJson(['success' => false]);

        $this->putJson('/api/user/update', ['ssn' => '29001011234500', 'job_title' => 'طبيب'])->assertOk();
        $this->putJson('/api/user/update', ['job_title' => 'آخر'])->assertStatus(409);
        $this->getJson('/api/user/update_is_pending/0')->assertJson(['success' => true]);
    }

    public function test_only_the_fields_the_member_changed_are_sent_for_review(): void
    {
        $member = Subscribers::factory()->create([
            'mobile_no' => 1012345678, 'address' => 'أسوان', 'ssn' => '29001011234500', 'birthdate' => '1990-05-01', 'job' => 'مهندس',
        ]);
        Sanctum::actingAs($member);

        // The app sends every field; only the phone and the address differ.
        $this->putJson('/api/user/update', [
            'name' => $member->name, 'mobile_no' => '01198765432', 'address' => 'القاهرة',
            'ssn' => '29001011234500', 'birth_date' => '1990-05-01', 'job_title' => 'مهندس',
        ])->assertOk();

        $staged = UserUpdateStaging::sole();
        $this->assertSame('01198765432', (string) $staged->mobile_no);
        $this->assertSame('القاهرة', $staged->address);
        $this->assertNull($staged->name);
        $this->assertNull($staged->ssn);
        $this->assertNull($staged->birth_date);
        $this->assertNull($staged->job_title);
    }

    public function test_the_same_phone_without_its_leading_zero_is_not_a_change(): void
    {
        $member = Subscribers::factory()->create(['mobile_no' => 1012345678]);
        Sanctum::actingAs($member);

        $this->putJson('/api/user/update', ['mobile_no' => '01012345678'])
            ->assertStatus(422)->assertJsonPath('message', 'لم تقم بتعديل أي بيانات.');
        $this->assertSame(0, UserUpdateStaging::count());
    }

    public function test_a_wrong_phone_is_reported_on_its_field(): void
    {
        Sanctum::actingAs(Subscribers::factory()->create());

        $this->putJson('/api/user/update', ['mobile_no' => '0101234'])
            ->assertStatus(422)
            ->assertJsonPath('errors.mobile_no.0', 'رقم الهاتف غير صحيح، اكتب 11 رقم يبدأ بـ 010 أو 011 أو 012 أو 015');
        $this->assertSame(0, UserUpdateStaging::count());
    }

    public function test_older_apps_sending_job_and_birthdate_still_work(): void
    {
        $member = Subscribers::factory()->create(['job' => 'مهندس', 'birthdate' => '1990-05-01']);
        Sanctum::actingAs($member);

        $this->putJson('/api/user/update', ['job' => 'طبيب', 'birthdate' => '1991-02-03'])->assertOk();

        $staged = UserUpdateStaging::sole();
        $this->assertSame('طبيب', $staged->job_title);
        $this->assertStringStartsWith('1991-02-03', (string) $staged->birth_date);
    }

    public function test_staff_approve_a_request_without_a_name(): void
    {
        $this->actingAsStaff('المستخدمين');
        $member = Subscribers::factory()->create(['name' => 'الاسم القديم']);
        UserUpdateStaging::create(['member_id' => $member->member_id, 'address' => 'القاهرة']);

        $this->postJson('/admin/users/update_request', ['member_id' => $member->member_id, 'name' => '', 'address' => 'القاهرة'])->assertOk();

        $this->assertSame('الاسم القديم', $member->fresh()->name);
        $this->assertSame('القاهرة', $member->fresh()->address);
    }

    public function test_approving_an_old_style_request_with_the_phone_missing_its_zero_works(): void
    {
        $this->actingAsStaff('المستخدمين');
        $member = Subscribers::factory()->create(['mobile_no' => 1012345678, 'address' => 'أسوان']);
        // Requests saved before only-changes were stored copy every field,
        // including the phone as the database keeps it (no leading 0).
        UserUpdateStaging::create(['member_id' => $member->member_id, 'name' => $member->name, 'mobile_no' => '1012345678', 'address' => 'القاهرة']);

        $this->postJson('/admin/users/update_request', [
            'member_id' => $member->member_id, 'name' => $member->name, 'mobile_no' => '1012345678', 'address' => 'القاهرة',
            'ssn' => '', 'job_title' => '', 'birthdate' => '',
        ])->assertOk();

        $this->assertSame('القاهرة', $member->fresh()->address);
        $this->assertSame(0, UserUpdateStaging::count());
    }

    public function test_member_report_pages_render(): void
    {
        $this->actingAsStaff('التقارير', 'المستخدمين');
        Subscribers::factory()->create(['job' => 'مهندس', 'birthdate' => '1990-05-01', 'address' => 'أسوان']);

        foreach (['reports/jobs?search=مهندس', 'reports/jobs', 'reports/age?birthdate=1990-01-01', 'reports/location?address=أسوان', 'reports/incomplete', 'reports/associates', 'reports/subscribers_data', 'users/update_requests'] as $page) {
            $this->get('/admin/'.$page)->assertOk();
        }
    }
}
