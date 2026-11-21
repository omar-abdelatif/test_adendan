<?php

namespace Tests\Feature\Members;

use App\Models\CostYears;
use App\Models\Due;
use App\Models\PaymentTransaction;
use App\Models\Subscribers;
use App\Models\TotalSafe;
use App\Modules\Finance\Domain\FiscalCalendar;
use App\Modules\Members\Application\Actions\IssueAppPasswords;
use App\Modules\Messaging\Application\Jobs\SendSms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\ActsAsStaff;
use Tests\TestCase;

class MembersTest extends TestCase
{
    use ActsAsStaff, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TotalSafe::create(['amount' => 0]);
    }

    private function form(array $overrides = []): array
    {
        return array_merge([
            'member_id' => 501, 'name' => 'محمد أحمد', 'ssn' => '29001011234567', 'mobile_no' => '01012345678',
            'birthdate' => '1990-01-01', 'address' => 'أدندان', 'inv_no' => 1200,
        ], $overrides);
    }

    public function test_registering_a_member_records_fees_in_the_safe_and_starting_dues(): void
    {
        $this->actingAsStaff('المشتركين');
        $year = app(FiscalCalendar::class)->subscriptionYear(now());
        CostYears::factory()->create(['year' => $year, 'cost' => 360]);
        $months = app(FiscalCalendar::class)->remainingMonths(now());

        $this->post('/admin/subscribers/store', $this->form())->assertSessionHasNoErrors()->assertSessionHas('alert-type', 'success');

        $member = Subscribers::sole();
        $this->assertSame(1, (int) $member->status);
        $this->assertStringEndsWith('-501', $member->slug);
        $first = 360 / 12 * $months + 50;
        $this->assertEqualsCanonicalizing([(int) round($first), 40], PaymentTransaction::pluck('amount')->all());
        // Both payments are cash in the safe (the annual donation used to be left out).
        $this->assertSame((int) round($first) + 40, (int) TotalSafe::first()->amount);
        $this->assertSame(2, Due::where('member_id', 501)->where('transaction_type', 'تبرعات')->count());
    }

    public function test_registration_needs_this_years_price(): void
    {
        $this->actingAsStaff('المشتركين');

        $this->post('/admin/subscribers/store', $this->form())->assertSessionHas('alert-type', 'error');

        $this->assertSame(0, Subscribers::count());
    }

    public function test_national_id_and_mobile_must_be_unique(): void
    {
        $this->actingAsStaff('المشتركين');
        Subscribers::factory()->create(['ssn' => 29001011234567, 'mobile_no' => 1012345678]);

        $this->post('/admin/subscribers/store', $this->form())->assertSessionHasErrors(['ssn', 'mobile_no']);
    }

    public function test_marking_a_member_deceased_clears_their_dues(): void
    {
        $this->actingAsStaff('المشتركين');
        $member = Subscribers::factory()->create(['member_id' => 501]);
        Due::factory()->forMember($member)->count(2)->create();

        $this->post('/admin/subscribers/update', $this->form([
            'id' => $member->id, 'ssn' => (string) $member->ssn, 'mobile_no' => '0'.$member->mobile_no, 'status' => 'on', 'name' => 'اسم جديد',
        ]))->assertSessionHasNoErrors();

        $this->assertSame(2, (int) $member->fresh()->status);
        $this->assertSame('اسم جديد', $member->fresh()->name);
        $this->assertSame(0, Due::count());
    }

    public function test_a_member_with_payments_cannot_be_deleted(): void
    {
        $this->actingAsStaff('المشتركين');
        $member = Subscribers::factory()->create();
        PaymentTransaction::factory()->create(['member_id' => $member->member_id]);

        $this->get("/admin/subscribers/delete/{$member->id}")->assertSessionHas('alert-type', 'error');

        $this->assertNotNull($member->fresh());
        $this->assertSame(1, PaymentTransaction::count());
    }

    public function test_a_member_without_payments_can_be_deleted(): void
    {
        $this->actingAsStaff('المشتركين');
        $member = Subscribers::factory()->create();
        Due::factory()->forMember($member)->create();

        $this->get("/admin/subscribers/delete/{$member->id}")->assertSessionHas('alert-type', 'success');

        $this->assertNull($member->fresh());
        $this->assertSame(0, Due::count());
    }

    public function test_member_table_escapes_names(): void
    {
        $this->actingAsStaff('المشتركين');
        Subscribers::factory()->create(['name' => '<script>alert(1)</script>']);

        $html = $this->getJson('/admin/subscribers/subscribers/ajax/data/subscribers?draw=1&start=0&length=10')
            ->assertOk()
            ->json('data.0.Actions');

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_members_page_renders(): void
    {
        $this->actingAsStaff('المشتركين');
        $member = Subscribers::factory()->create();

        $this->get('/admin/subscribers/subscribers/all')->assertOk();
        $this->get("/admin/subscribers/update/{$member->id}")->assertOk();
    }

    public function test_app_passwords_are_random_and_sent_by_sms(): void
    {
        Bus::fake();
        $this->actingAsStaff('المشتركين');
        $member = Subscribers::factory()->create(['password' => null]);

        $this->postJson('/admin/subscribers/generate-passwords')->assertOk()->assertJson(['count' => 1]);

        $member->refresh();
        $this->assertNotNull($member->password);
        $this->assertFalse(Hash::check(IssueAppPasswords::legacyPassword($member), $member->password));
        Bus::assertDispatched(SendSms::class, fn (SendSms $job) => $job->number === (string) $member->mobile_no);
    }

    public function test_guessable_passwords_are_found_and_replaced(): void
    {
        Bus::fake();
        $weak = Subscribers::factory()->create();
        $weak->forceFill(['password' => Hash::make(IssueAppPasswords::legacyPassword($weak))])->save();
        Subscribers::factory()->create()->forceFill(['password' => Hash::make('strong-random')])->save();

        $this->artisan('members:replace-guessable-passwords')->expectsOutputToContain('1 members')->assertSuccessful();
        Bus::assertNothingDispatched();

        $this->artisan('members:replace-guessable-passwords --force')->assertSuccessful();
        $this->assertFalse(Hash::check(IssueAppPasswords::legacyPassword($weak), $weak->fresh()->password));
        Bus::assertDispatchedTimes(SendSms::class, 1);
    }
}
