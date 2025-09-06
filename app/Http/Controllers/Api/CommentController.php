<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CommentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function store(Request $request, Post $post)
    {
        $validator = Validator::make($request->all(), [
            'body' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $comment = $post->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $request->body,
        ]);

        $comment->load('user'); // Eager load the user relationship

        return response()->json($comment, 201);
    }

    public function destroy(Comment $comment)
    {
        // Authorize that the user deleting the comment is the one who created it
        $this->authorize('delete', $comment);

        $comment->delete();

        return response()->json(null, 204);
    }
}
