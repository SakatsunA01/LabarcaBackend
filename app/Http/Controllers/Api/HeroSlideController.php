<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HeroSlide;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class HeroSlideController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum')->except(['index', 'show']);
    }

    public function index()
    {
        $slides = HeroSlide::orderBy('order', 'asc')->get();
        return response()->json($slides);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'button_text' => 'required|string|max:255',
            'button_url' => 'required|url',
            'order' => 'required|integer',
            'is_active' => 'required|boolean',
            'video' => 'required|file|mimetypes:video/mp4,video/quicktime|max:51200', // 50MB max
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $data = $request->except('video');

        if ($request->hasFile('video')) {
            $path = $request->file('video')->store('hero_videos', 'public');
            $data['video_path'] = '/public/storage/' . $path;
        }

        $slide = HeroSlide::create($data);

        return response()->json($slide, 201);
    }

    public function show(string $id)
    {
        $slide = HeroSlide::find($id);
        if (is_null($slide)) {
            return response()->json(['message' => 'Slide no encontrado'], 404);
        }
        return response()->json($slide);
    }

    public function update(Request $request, string $id)
    {
        $slide = HeroSlide::find($id);
        if (is_null($slide)) {
            return response()->json(['message' => 'Slide no encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'button_text' => 'sometimes|required|string|max:255',
            'button_url' => 'sometimes|required|url',
            'order' => 'sometimes|required|integer',
            'is_active' => 'sometimes|required|boolean',
            'video' => 'nullable|file|mimetypes:video/mp4,video/quicktime|max:51200', // 50MB max
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $data = $request->except(['video', '_method']);

        if ($request->hasFile('video')) {
            $this->deleteFile($slide->video_path);
            $path = $request->file('video')->store('hero_videos', 'public');
            $data['video_path'] = '/public/storage/' . $path;
        }

        $slide->update($data);

        return response()->json($slide);
    }

    public function destroy(string $id)
    {
        $slide = HeroSlide::find($id);
        if (is_null($slide)) {
            return response()->json(['message' => 'Slide no encontrado'], 404);
        }

        $this->deleteFile($slide->video_path);

        $slide->delete();

        return response()->json(null, 204);
    }

    private function deleteFile($filePath)
    {
        if (!$filePath) {
            return;
        }
        $path = parse_url($filePath, PHP_URL_PATH);
        if ($path) {
$path = str_replace('/public/storage/', '', $path);
            Storage::disk('public')->delete($path);
        }
    }
}
