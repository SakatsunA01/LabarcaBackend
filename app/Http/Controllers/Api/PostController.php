<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class PostController extends Controller
{
    public function __construct()
    {
        // Proteger las rutas que modifican datos (crear, actualizar, eliminar)
        $this->middleware('auth:sanctum')->except(['index', 'show', 'latest']);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $posts = Post::with('categories')->withCount('likes')->whereNotNull('fecha_publicacion')
                     ->orderBy('fecha_publicacion', 'desc')
                     ->get();
        return response()->json($posts);
    }

    /**
     * Display a listing of the latest resource.
     */
    public function latest()
    {
        $posts = Post::with('categories')->withCount('likes')->whereNotNull('fecha_publicacion')
                     ->orderBy('fecha_publicacion', 'desc')
                     ->take(3)
                     ->get();
        return response()->json($posts);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'titulo' => 'required|string|max:255',
            'contenido' => 'required|string',
            'autor' => 'nullable|string|max:255',
            'imagen_file' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:10240', // 10MB
            'fecha_publicacion' => 'nullable|date',
            'categories' => 'nullable|array',
            'categories.*' => 'exists:categories,id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $data = $request->except(['imagen_file', 'categories']);

        if ($request->hasFile('imagen_file')) {
            $data['url_imagen'] = $this->handleImageUpload($request, 'imagen_file');
        }

        if (isset($data['fecha_publicacion'])) {
            $data['fecha_publicacion'] = $data['fecha_publicacion'] ?: now();
        } else {
            $data['fecha_publicacion'] = now();
        }

        $post = Post::create($data);

        if ($request->has('categories')) {
            $post->categories()->sync($request->categories);
        }

        $post->load('categories'); // Cargar las categorías para devolverlas en la respuesta

        return response()->json($post, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $post = Post::with('categories', 'comments.user')->withCount('likes')->find($id);
        if (is_null($post)) {
            return response()->json(['message' => 'Post no encontrado'], 404);
        }
        return response()->json($post);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $post = Post::find($id);
        if (is_null($post)) {
            return response()->json(['message' => 'Post no encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'titulo' => 'sometimes|required|string|max:255',
            'contenido' => 'sometimes|required|string',
            'autor' => 'nullable|string|max:255',
            'imagen_file' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:10240', // 10MB
            'fecha_publicacion' => 'nullable|date',
            'categories' => 'nullable|array',
            'categories.*' => 'exists:categories,id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $data = $request->except(['imagen_file', '_method', 'categories']);

        if ($request->hasFile('imagen_file')) {
            $data['url_imagen'] = $this->handleImageUpload($request, 'imagen_file', $post->url_imagen);
        }

        $post->update($data);

        if ($request->has('categories')) {
            $post->categories()->sync($request->categories);
        }

        $post->load('categories');

        return response()->json($post);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $post = Post::find($id);
        if (is_null($post)) {
            return response()->json(['message' => 'Post no encontrado'], 404);
        }

        if ($post->url_imagen) {
            $this->deleteImage($post->url_imagen);
        }

        $post->delete();

        return response()->json(null, 204);
    }

    private function handleImageUpload(Request $request, $fieldName, $oldImagePath = null)
    {
        if ($request->hasFile($fieldName)) {
            if ($oldImagePath) {
                $this->deleteImage($oldImagePath);
            }
            $path = $request->file($fieldName)->store('posts_images', 'public');
            return Storage::url($path);
        }
        return $oldImagePath; // Devuelve la imagen antigua si no se sube una nueva
    }

    private function deleteImage($imagePath)
    {
        if (!$imagePath) {
            return;
        }
        $path = parse_url($imagePath, PHP_URL_PATH);
        if ($path) {
$path = str_replace('/public/storage/', '', $path);
            Storage::disk('public')->delete($path);
        }
    }
}