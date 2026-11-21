<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\News;

class NewsController extends Controller
{
    public function allNews()
    {
        return response()->json(News::all());
    }

    public function show(int $id)
    {
        return response()->json(News::find($id));
    }

    public function latestNews()
    {
        return response()->json(News::latest()->limit(6)->get());
    }
}
