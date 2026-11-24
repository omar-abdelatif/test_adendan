<?php

namespace Tests\Feature\Content;

use App\Models\AssociationCommittes;
use App\Models\BoardMembers;
use App\Models\News;
use App\Models\NewsVideos;
use App\Modules\Messaging\Application\Jobs\PushToAllMembers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\Concerns\ActsAsStaff;
use Tests\TestCase;

class ContentTest extends TestCase
{
    use ActsAsStaff, RefreshDatabase;

    public function test_publishing_news_cleans_html_validates_videos_and_queues_the_push(): void
    {
        Bus::fake();
        $this->actingAsStaff('الاخبار');

        $this->post('/admin/news/store_news', [
            'title' => 'خبر', 'category' => 'أخبار عامة',
            'description' => '<p>نص</p><script>alert(1)</script>',
            'url' => ['https://evil.example/x'],
        ])->assertSessionHasErrors('url.0');

        $this->post('/admin/news/store_news', [
            'title' => 'خبر', 'category' => 'أخبار عامة',
            'description' => '<p>نص</p><script>alert(1)</script>',
            'url' => ['https://youtu.be/abc123XYZ'],
        ])->assertSessionHasNoErrors();

        $news = News::sole();
        $this->assertStringNotContainsString('<script', $news->description);
        $this->assertSame(1, NewsVideos::count());
        Bus::assertDispatched(PushToAllMembers::class, fn ($job) => $job->body === 'خبر');
    }

    public function test_unknown_category_is_rejected(): void
    {
        $this->actingAsStaff('الاخبار');

        $this->post('/admin/news/store_news', ['title' => 'خبر', 'category' => 'التصنيف', 'description' => 'نص'])
            ->assertSessionHasErrors('category');
    }

    public function test_old_unsafe_news_html_is_cleaned_on_the_public_page(): void
    {
        $news = News::factory()->create(['description' => '<p>قديم</p><img src=x onerror="alert(1)">']);

        $this->get("/all_news/single_news/{$news->slug}")
            ->assertOk()
            ->assertSee('قديم', false)
            ->assertDontSee('onerror', false);
    }

    public function test_board_and_committees_management(): void
    {
        $this->actingAsStaff('مجلس الادارة', 'اللجان');

        $this->post('/admin/board/board/store', ['name' => 'عضو', 'position' => 'رئيس', 'phone_number' => '1012345678'])
            ->assertSessionHasErrors('img');

        $this->post('/admin/association/associations/store_association', ['name' => 'لجنة', 'boss' => 'رئيس', 'description' => 'وصف', 'tasks' => 'مهام'])
            ->assertSessionHasNoErrors();
        $committee = AssociationCommittes::sole();
        $this->post('/admin/association/associations/update_association', ['id' => $committee->id, 'name' => 'لجنة 2', 'boss' => 'رئيس', 'description' => 'وصف', 'tasks' => 'مهام'])
            ->assertSessionHasNoErrors();
        $this->assertSame('لجنة 2', $committee->fresh()->name);
        $this->assertSame(0, BoardMembers::count());
    }

    public function test_public_pages_render(): void
    {
        News::factory()->count(3)->create();
        $committee = AssociationCommittes::create(['name' => 'لجنة', 'boss' => 'رئيس', 'description' => 'وصف', 'tasks' => 'مهام']);

        foreach (['/', '/all_news', '/board_members', '/assossiation_committees', "/assossiation_committees/assossiation_details/{$committee->id}", '/search', '/workers/all', '/about', '/contact_us', '/donate_page'] as $page) {
            $this->get($page)->assertOk();
        }
        $this->getJson('/api/news/all')->assertOk()->assertJsonCount(3);
        $this->getJson('/api/all_tombs')->assertOk();
    }

    public function test_admin_content_pages_render(): void
    {
        $this->actingAsStaff('الاخبار', 'مجلس الادارة', 'اللجان', 'المقابر', 'الافراح', 'الحرفيين', 'المتبرعين');
        $news = News::factory()->create();

        foreach (['news/all', "news/all_news/all_thumbs/{$news->id}", 'board/board/all', 'association/associations/all', 'cemeteries', 'cemeteries/burials', 'weddings/weddings/all', 'workers/workers/all', 'donators/donators/all'] as $page) {
            $this->get('/admin/'.$page)->assertOk();
        }
    }
}
