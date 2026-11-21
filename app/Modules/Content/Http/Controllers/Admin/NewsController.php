<?php

namespace App\Modules\Content\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\News;
use App\Models\NewsThumbnail;
use App\Models\NewsVideos;
use App\Modules\Content\Application\ManageNews;
use App\Modules\Content\Http\Requests\NewsRequest;
use App\Modules\Shared\Http\Concerns\RespondsWithAlerts;
use Illuminate\Http\Request;

class NewsController extends Controller
{
    use RespondsWithAlerts;

    public function __construct(private readonly ManageNews $news)
    {
        $this->middleware('permission:الاخبار');
    }

    public function index()
    {
        return view('pages.news.all', ['news' => News::select('id', 'title', 'slug', 'category', 'img', 'description', 'created_at')->latest()->get()]);
    }

    public function store(NewsRequest $request)
    {
        $this->news->publish(
            $request->fields(),
            $request->file('img'),
            $request->file('thumbnail', []),
            $request->input('url', []),
            $request->user()->name,
        );

        return $this->success('تم الإضافة بنجاح');
    }

    public function update(NewsRequest $request)
    {
        $this->news->update(News::findOrFail($request->integer('id')), $request->fields(), $request->file('img'), $request->file('thumbnail', []));

        return $this->success('تم التحديث بنجاح');
    }

    public function destroy(News $news)
    {
        $this->news->delete($news);

        return $this->success('تم الحذف بنجاح');
    }

    public function media(News $news)
    {
        $thumbnails = $news->thumbnails;
        $videos = $news->videos;

        return view('pages.news.single', [
            'news' => $news,
            'thumbnails' => $thumbnails,
            'count' => $thumbnails->count(),
            'videos' => $videos,
            'vidCount' => $videos->count(),
        ]);
    }

    public function destroyImage(NewsThumbnail $image)
    {
        $this->news->deleteGalleryImage($image);

        return redirect()->route('show.thumbs', $image->news_id)->with(['message' => 'تم الحذف بنجاح', 'alert-type' => 'success']);
    }

    public function storeVideos(Request $request, News $news)
    {
        $request->validate(['url' => ['required', 'array', 'max:10'], 'url.*' => NewsRequest::videoRule()]);
        $this->news->addVideos($news, $request->input('url'));

        return redirect()->route('show.thumbs', $news->id)->with(['message' => 'تم الإضافة بنجاح', 'alert-type' => 'success']);
    }

    public function updateVideo(Request $request)
    {
        $request->validate(['id' => ['required', 'integer', 'exists:news_videos,id'], 'url' => ['required', ...NewsRequest::videoRule()]]);
        NewsVideos::findOrFail($request->integer('id'))->update(['url' => trim($request->string('url'))]);

        return $this->success('تم التحديث بنجاح');
    }

    public function destroyVideo(NewsVideos $video)
    {
        $video->delete();

        return $this->success('تم الحذف بنجاح');
    }
}
