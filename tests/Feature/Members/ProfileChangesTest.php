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

    public function test_member_report_pages_render(): void
    {
        $this->actingAsStaff('التقارير', 'المستخدمين');
        Subscribers::factory()->create(['job' => 'مهندس', 'birthdate' => '1990-05-01', 'address' => 'أسوان']);

        foreach (['reports/jobs?search=مهندس', 'reports/jobs', 'reports/age?birthdate=1990-01-01', 'reports/location?address=أسوان', 'reports/incomplete', 'reports/associates', 'reports/subscribers_data', 'users/update_requests'] as $page) {
            $this->get('/admin/'.$page)->assertOk();
        }
    }
}
