<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Request;

class LikeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function store(Request $request, Post $post)
    {
        $like = $post->likes()->firstOrCreate([
            'user_id' => $request->user()->id,
        ]);

        return response()->json($like, 201);
    }

    public function destroy(Request $request, Post $post)
    {
        $post->likes()->where('user_id', $request->user()->id)->delete();

        return response()->json(null, 204);
    }
}
