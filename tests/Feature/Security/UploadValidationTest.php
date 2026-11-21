<?php

namespace Tests\Feature\Security;

use App\Models\News;
use App\Models\NewsThumbnail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class UploadValidationTest extends TestCase
{
    use RefreshDatabase;

    private array $createdFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->createdFiles as $path) {
            @unlink($path);
        }
        parent::tearDown();
    }

    private function staff(string $permission): User
    {
        $user = User::create(['name' => 'Staff', 'email' => uniqid().'@example.test', 'password' => bcrypt('secret')]);
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));

        return $user;
    }

    public function test_new_member_form_rejects_a_php_file_as_photo(): void
    {
        $shell = UploadedFile::fake()->createWithContent('shell.php', '<?php system($_GET["c"]); ?>');

        $this->actingAs($this->staff('المشتركين'))
            ->post('/admin/subscribers/store', [
                'ssn' => '29001011234567', 'mobile_no' => '01012345678', 'name' => 'عضو', 'img' => $shell,
            ])
            ->assertSessionHasErrors('img');

        $this->assertDatabaseCount('subscribers', 0);
    }

    public function test_real_image_disguised_with_php_name_is_rejected(): void
    {
        $news = $this->news();
        $disguised = UploadedFile::fake()->image('shell.php');

        $this->actingAs($this->staff('الاخبار'))
            ->post('/admin/news/update_news', $this->newsForm($news, [$disguised]))
            ->assertSessionHasErrors('thumbnail.0');

        $this->assertDatabaseCount('news_thumbnails', 0);
    }

    public function test_valid_gallery_image_is_saved_with_its_real_extension(): void
    {
        $news = $this->news();
        // Client says .jpg, content is PNG: the stored name follows the content.
        // A real (non-fake) upload, so the type is read from the bytes like in production.
        $path = tempnam(sys_get_temp_dir(), 'img');
        imagepng(imagecreatetruecolor(10, 10), $path);
        $png = new UploadedFile($path, 'photo.jpg', null, null, true);

        $this->actingAs($this->staff('الاخبار'))
            ->post('/admin/news/update_news', $this->newsForm($news, [$png]))
            ->assertSessionHasNoErrors();

        $saved = NewsThumbnail::sole()->thumbnail;
        $this->createdFiles[] = public_path('assets/images/news-imgs/news-thumbnails/'.$saved);
        $this->assertStringEndsWith('.png', $saved);
    }

    private function news(): News
    {
        return News::forceCreate(['title' => 'خبر', 'description' => 'نص', 'category' => 'عام', 'posted_by' => 'admin']);
    }

    private function newsForm(News $news, array $thumbnails): array
    {
        return ['id' => $news->id, 'title' => 'خبر', 'description' => 'نص', 'category' => 'أخبار عامة', 'thumbnail' => $thumbnails];
    }
}
