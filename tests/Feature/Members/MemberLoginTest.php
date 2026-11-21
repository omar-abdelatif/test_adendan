<?php

namespace Tests\Feature\Members;

use App\Models\Subscribers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MemberLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_logs_in_reads_profile_and_logs_out(): void
    {
        $member = Subscribers::factory()->create(['ssn' => 29001011234567]);
        $member->forceFill(['password' => Hash::make('pass1234')])->save();

        $this->postJson('/api/member/login', ['ssn' => '29001011234567', 'password' => 'wrong'])->assertUnauthorized();

        $token = $this->postJson('/api/member/login', ['ssn' => '29001011234567', 'password' => 'pass1234'])
            ->assertOk()->json('access_token');

        $this->withToken($token)->getJson('/api/user/me')->assertOk()->assertJsonPath('member_id', $member->member_id)
            ->assertJsonMissingPath('password');
        $this->withToken($token)->postJson('/api/user/logout')->assertOk();

        $this->assertSame(0, $member->tokens()->count());
    }

    public function test_member_without_a_password_cannot_log_in(): void
    {
        Subscribers::factory()->create(['ssn' => 29001011234567, 'password' => null]);

        $this->postJson('/api/member/login', ['ssn' => '29001011234567', 'password' => ''])->assertStatus(422);
        $this->postJson('/api/member/login', ['ssn' => '29001011234567', 'password' => 'anything'])->assertUnauthorized();
    }
}
