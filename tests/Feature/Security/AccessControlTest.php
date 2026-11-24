<?php

namespace Tests\Feature\Security;

use App\Models\CemeteryRegion;
use App\Models\CemeteryTomb;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    private function staff(array $permissions = []): User
    {
        $user = User::create(['name' => 'Staff', 'email' => uniqid().'@example.test', 'password' => bcrypt('secret')]);
        foreach ($permissions as $name) {
            $user->givePermissionTo(Permission::findOrCreate($name, 'web'));
        }

        return $user;
    }

    public function test_public_registration_is_closed(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [
            'name' => 'Outsider', 'email' => 'out@example.test',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertNotFound();

        $this->assertDatabaseMissing('users', ['email' => 'out@example.test']);
    }

    public function test_staff_without_sms_permission_cannot_reach_sms_pages(): void
    {
        $this->actingAs($this->staff())->get('/admin/sms/sms')->assertForbidden();
        $this->actingAs($this->staff())->post('/admin/sms/store_sms_fees', ['amount' => 1])->assertForbidden();
    }

    public function test_staff_without_subscribers_permission_cannot_import_payments(): void
    {
        $file = UploadedFile::fake()->create('payments.xlsx', 10);

        $this->actingAs($this->staff())
            ->post('/admin/import-payment-transactions', ['import-transactions' => $file])
            ->assertForbidden();
    }

    public function test_staff_without_reports_permission_cannot_approve_member_data(): void
    {
        $this->actingAs($this->staff())
            ->post('/admin/searched_data/approve', ['member_id' => 1, 'ssn' => '29001011234567'])
            ->assertForbidden();
    }

    public function test_deleting_needs_a_delete_request_not_a_link(): void
    {
        $this->actingAs($this->staff(['المقابر']));
        $region = CemeteryRegion::create(['name' => 'أدندان', 'slug' => 'adendan']);
        $tomb = CemeteryTomb::create(['region_id' => $region->id, 'name' => 'مقبرة', 'kind' => 'lahd', 'serves' => 'men']);

        // A GET (a link or an <img src>) can no longer delete anything.
        $this->get("/admin/cemeteries/tombs/{$tomb->id}")->assertOk();
        $this->assertNotNull($tomb->fresh());

        $this->delete("/admin/cemeteries/tombs/{$tomb->id}")->assertSessionHas('alert-type', 'success');
        $this->assertNull($tomb->fresh());
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/admin/sms/sms')->assertRedirect('/login');
    }
}
