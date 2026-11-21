<?php

namespace App\Modules\Content\Application;

use App\Models\News;
use App\Models\NewsThumbnail;
use App\Models\NewsVideos;
use App\Modules\Content\Infrastructure\HtmlSanitizer;
use App\Modules\Messaging\Application\Jobs\PushToAllMembers;
use App\Modules\Shared\Infrastructure\ImageStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * News with a cover image, a gallery and YouTube videos. Publishing notifies
 * the members' app (queued, so the page does not wait).
 */
class ManageNews
{
    public const COVER_FOLDER = 'news-imgs';

    public const GALLERY_FOLDER = 'news-imgs/news-thumbnails';

    public function __construct(private readonly ImageStorage $images, private readonly HtmlSanitizer $html) {}

    /**
     * @param  array{title: string, description: string, category: string}  $fields
     * @param  list<UploadedFile>  $gallery
     * @param  list<string>  $videoUrls
     */
    public function publish(array $fields, ?UploadedFile $cover, array $gallery, array $videoUrls, string $author): News
    {
        $news = DB::transaction(function () use ($fields, $cover, $gallery, $videoUrls, $author) {
            $news = News::create([
                ...$this->clean($fields),
                'img' => $cover ? $this->images->store($cover, self::COVER_FOLDER) : null,
                'slug' => uniqid(),
                'posted_by' => $author,
            ]);
            $this->addGallery($news, $gallery);
            $this->addVideos($news, $videoUrls);

            return $news;
        });

        PushToAllMembers::dispatch('خبر جديد', $news->title, ['type' => 'news', 'news_id' => (string) $news->id]);

        return $news;
    }

    /**
     * @param  array{title: string, description: string, category: string}  $fields
     * @param  list<UploadedFile>  $gallery
     */
    public function update(News $news, array $fields, ?UploadedFile $cover, array $gallery): News
    {
        if ($cover) {
            $news->img = $this->images->replace($news->img, $cover, self::COVER_FOLDER);
        }
        $news->fill($this->clean($fields))->save();
        $this->addGallery($news, $gallery);

        return $news;
    }

    public function delete(News $news): void
    {
        $gallery = $news->thumbnails()->pluck('thumbnail');

        DB::transaction(function () use ($news) {
            $news->thumbnails()->delete();
            $news->videos()->delete();
            $news->delete();
        });

        $this->images->delete($news->img, self::COVER_FOLDER);
        foreach ($gallery as $name) {
            $this->images->delete($name, self::GALLERY_FOLDER);
        }
    }

    /** @param list<UploadedFile> $files */
    public function addGallery(News $news, array $files): void
    {
        foreach ($files as $file) {
            NewsThumbnail::create(['news_id' => $news->id, 'thumbnail' => $this->images->store($file, self::GALLERY_FOLDER)]);
        }
    }

    public function deleteGalleryImage(NewsThumbnail $image): void
    {
        $image->delete();
        $this->images->delete($image->thumbnail, self::GALLERY_FOLDER);
    }

    /** @param list<string> $urls */
    public function addVideos(News $news, array $urls): void
    {
        foreach (array_filter($urls) as $url) {
            NewsVideos::create(['news_id' => $news->id, 'url' => trim($url)]);
        }
    }

    /** @param array{title: string, description: string, category: string} $fields */
    private function clean(array $fields): array
    {
        return [
            'title' => trim($fields['title']),
            'description' => $this->html->clean($fields['description']),
            'category' => $fields['category'],
        ];
    }
}
