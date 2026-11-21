<?php

namespace Tests\Feature\Security;

use App\Models\Due;
use App\Models\News;
use App\Models\Subscribers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PublicEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private function member(int $memberId, int $ssn): Subscribers
    {
        return Subscribers::create([
            'member_id' => $memberId, 'name' => "عضو {$memberId}", 'slug' => "member-{$memberId}",
            'ssn' => $ssn, 'birthdate' => '1990-01-01', 'mobile_no' => 1012345600 + $memberId,
            'address' => 'عنوان خاص', 'job' => 'مهندس', 'status' => 1,
        ]);
    }

    public function test_national_id_search_returns_dues_but_no_personal_data(): void
    {
        $this->member(15, 29001011234567);
        Due::create(['member_id' => 15, 'item' => '2026', 'transaction_type' => 'إشتراك', 'total_amount' => 300, 'amount_paid' => 0, 'amount_remaining' => 300]);

        $response = $this->postJson('/result', ['ssn' => '29001011234567'])->assertOk();

        $this->assertSame(['name', 'member_id', 'status'], array_keys($response->json('data.member')));
        $this->assertSame(300, (int) $response->json('data.subsDues.0.amount_remaining'));
        $this->assertStringNotContainsString('عنوان خاص', $response->getContent());
        $this->assertStringNotContainsString('29001011234567', $response->getContent());
    }

    public function test_national_id_search_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/result', ['ssn' => "2900101123{$i}000"]);
        }

        $this->postJson('/result', ['ssn' => '29001011239999'])->assertStatus(429);
    }

    public function test_ocr_endpoint_needs_the_engine_key(): void
    {
        $data = ['name' => 'اسم', 'address' => 'عنوان', 'nid' => '29001011234567', 'birth_date' => '1990-01-01', 'mobile' => '01012345678'];

        $this->postJson('/api/ocr-data', $data)->assertUnauthorized();
        $this->postJson('/api/ocr-data', $data, ['X-Api-Key' => 'wrong'])->assertUnauthorized();
        $this->postJson('/api/ocr-data', $data, ['X-Api-Key' => 'test-ocr-key'])->assertCreated();

        $this->assertDatabaseCount('ocr_data', 1);
    }

    public function test_member_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/member/login', ['ssn' => '29001011234567', 'password' => 'guess' . $i]);
        }

        $this->postJson('/api/member/login', ['ssn' => '29001011234567', 'password' => 'guess'])->assertStatus(429);
    }

    public function test_guests_cannot_edit_or_delete_comments(): void
    {
        $commentId = $this->comment(ownerId: null);

        $this->putJson("/api/news/1/comments/{$commentId}", ['content' => 'تم التلاعب'])->assertUnauthorized();
        $this->deleteJson("/api/news/1/comments/{$commentId}")->assertUnauthorized();

        $this->assertDatabaseHas('news_comments', ['id' => $commentId]);
    }

    public function test_a_member_cannot_edit_or_delete_another_members_comment(): void
    {
        $owner = $this->member(15, 29001011234567);
        $other = $this->member(16, 29001011234568);
        $commentId = $this->comment(ownerId: $owner->id);

        Sanctum::actingAs($other);
        $this->putJson("/api/news/1/comments/{$commentId}", ['content' => 'تم التلاعب'])->assertForbidden();
        $this->deleteJson("/api/news/1/comments/{$commentId}")->assertForbidden();

        $this->assertDatabaseHas('news_comments', ['id' => $commentId]);
    }

    public function test_a_member_can_delete_their_own_comment(): void
    {
        $owner = $this->member(15, 29001011234567);
        $commentId = $this->comment(ownerId: $owner->id);

        Sanctum::actingAs($owner);
        $this->deleteJson("/api/news/1/comments/{$commentId}")->assertOk();

        $this->assertDatabaseMissing('news_comments', ['id' => $commentId]);
    }

    /** Inserted directly: the table's text column is `comment` while the code writes `content` (open question). */
    private function comment(?int $ownerId): int
    {
        if (!News::find(1)) {
            News::forceCreate(['id' => 1, 'title' => 'خبر', 'description' => 'نص', 'category' => 'عام', 'posted_by' => 'admin']);
        }
        return DB::table('news_comments')->insertGetId([
            'author' => 'عضو', 'comment' => 'تعليق', 'news_id' => 1, 'subscriber_id' => $ownerId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
