<?php

namespace App\Modules\Content\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\AssociationCommittes;
use App\Models\BoardMembers;
use App\Models\News;
use App\Modules\Content\Application\SiteNews;
use App\Modules\Content\Domain\YouTubeLink;
use App\Modules\Content\Infrastructure\HtmlSanitizer;
use Jorenvh\Share\Share;

/** Public website pages: home, news, board, committees. */
class SiteController extends Controller
{
    public function __construct(private readonly SiteNews $news) {}

    public function home()
    {
        return view('frontend.master', $this->news->homePage());
    }

    public function news()
    {
        return view('frontend.pages.news.all_news', $this->news->byCategory());
    }

    public function newsDetails(string $slug, HtmlSanitizer $html)
    {
        $news = News::where('slug', $slug)->firstOrFail();
        // Stored HTML is cleaned when saved; clean again for rows saved before that.
        $news->description = $html->clean($news->description);
        $this->describeImageShape($news);

        $thumbsImgs = $news->thumbnails()->get();
        $videoLinks = $news->videos()->pluck('url')->map(fn (string $url) => YouTubeLink::videoId($url))->filter()->values()->all();

        return view('frontend.pages.news.single_news', [
            'news' => $news,
            'thumbsImgs' => $thumbsImgs,
            'countThumbsImgs' => $thumbsImgs->count(),
            'videoLinks' => $videoLinks,
            'countVideos' => count($videoLinks),
            'socialShare' => app(Share::class)->currentPage()->facebook()->whatsapp()->telegram(),
        ]);
    }

    public function board()
    {
        return view('frontend.pages.board_members', ['boards' => BoardMembers::all()]);
    }

    public function committees()
    {
        return view('frontend.pages.association_committees.all', ['associations' => AssociationCommittes::all()]);
    }

    public function committee(AssociationCommittes $committee)
    {
        return view('frontend.pages.association_committees.details', ['association' => $committee]);
    }

    /** The single-news layout differs for wide, tall and square cover images. */
    private function describeImageShape(News $news): void
    {
        $path = $news->img ? public_path('assets/images/news-imgs/'.basename($news->img)) : null;
        $size = $path && is_file($path) ? @getimagesize($path) : false;

        if (! $size || ! $size[1]) {
            $news->setAttribute('is_landscape', null);
            $news->setAttribute('is_portrait', null);
            $news->setAttribute('is_square', null);

            return;
        }

        $ratio = $size[0] / $size[1];
        $news->setAttribute('is_landscape', $ratio > 1.5);
        $news->setAttribute('is_portrait', $ratio < 1);
        $news->setAttribute('is_square', $ratio == 1);
    }
}
