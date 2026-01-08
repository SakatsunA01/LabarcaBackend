<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(Product::orderBy('created_at', 'desc')->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->validationRules());

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $data = $request->except(['image_url']);
        if ($request->hasFile('image_url')) {
            $data['image_url'] = $this->handleImageUpload($request, 'image_url');
        }

        $product = Product::create($data);
        return response()->json($product, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $product = Product::find($id);
        if (is_null($product)) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }
        return response()->json($product);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $product = Product::find($id);
        if (is_null($product)) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        $validator = Validator::make($request->all(), $this->validationRules(true));
        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $data = $request->except(['image_url', '_method']);
        if ($request->hasFile('image_url')) {
            $data['image_url'] = $this->handleImageUpload($request, 'image_url', $product->image_url);
        }

        $product->update($data);
        return response()->json($product);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $product = Product::find($id);
        if (is_null($product)) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        $this->deleteImage($product->image_url);
        $product->delete();
        return response()->json(null, 204);
    }

    private function validationRules($isUpdate = false)
    {
        $sometimes = $isUpdate ? 'sometimes|' : '';
        return [
            'name' => $sometimes . 'required|string|max:255',
            'description' => 'nullable|string',
            'price_ars' => $sometimes . 'required|numeric|min:0',
            'stock' => $sometimes . 'required|integer|min:0',
            'is_active' => 'nullable|boolean',
            'image_url' => $sometimes . 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:10240',
        ];
    }

    private function handleImageUpload(Request $request, $fieldName, $oldImagePath = null)
    {
        if ($request->hasFile($fieldName)) {
            if ($oldImagePath) {
                $this->deleteImage($oldImagePath);
            }
            $path = $request->file($fieldName)->store('products', 'public');
            return '/public/storage/' . $path;
        }

        return $oldImagePath;
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
