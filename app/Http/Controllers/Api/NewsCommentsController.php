<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewsComments;
use Illuminate\Http\Request;

class NewsCommentsController extends Controller
{
    public function getComments(int $newsId)
    {
        $comments = NewsComments::where('news_id', $newsId)->get();

        return response()->json($comments);
    }

    public function store(Request $request, int $newsId)
    {
        $validatedData = $request->validate([
            'author' => 'required|string|max:255',
            'content' => 'required|string',
        ]);
        $comment = NewsComments::create([
            'author' => $validatedData['author'],
            'content' => $validatedData['content'],
            'news_id' => $newsId,
            // Comments stay open to guests; a logged-in member becomes the comment's owner.
            // user()->id, not id(): Subscribers uses `ssn` as its auth identifier.
            'subscriber_id' => auth('sanctum')->user()?->id,
        ]);

        return response()->json([
            'message' => 'Comment added successfully',
            'comment' => $comment,
        ], 201);
    }

    public function update(Request $request, int $newsId, int $commentId)
    {
        $comment = NewsComments::where('id', $commentId)->where('news_id', $newsId)->firstOrFail();
        if (! $comment->isOwnedBy($request->user())) {
            return response()->json(['message' => 'غير مسموح لك بتعديل هذا التعليق'], 403);
        }
        $validatedData = $request->validate(['content' => 'required|string']);
        $comment->update([
            'content' => $validatedData['content'],
        ]);

        return response()->json([
            'message' => 'تم التعديل بنجاح',
            'data' => $comment->fresh(),
        ]);
    }

    public function destroy(Request $request, int $newsId, int $commentId)
    {
        $comment = NewsComments::where('id', $commentId)->where('news_id', $newsId)->firstOrFail();
        if (! $comment->isOwnedBy($request->user())) {
            return response()->json(['message' => 'غير مسموح لك بحذف هذا التعليق'], 403);
        }
        $deletedComment = $comment->replicate();
        $comment->delete();

        return response()->json([
            'message' => 'تم الحذف بنجاح',
            'data' => $deletedComment,
        ]);
    }
}
