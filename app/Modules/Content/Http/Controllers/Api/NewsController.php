<?php

namespace App\Modules\Content\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\News;
use Illuminate\Http\JsonResponse;

/** Mobile app news feed. */
class NewsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(News::latest()->get());
    }

    public function latest(): JsonResponse
    {
        return response()->json(News::latest()->limit(6)->get());
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(News::with(['thumbnails', 'videos'])->findOrFail($id));
    }
}
