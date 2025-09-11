<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GaleriaEvento;
use App\Models\Evento;
use Illuminate\Support\Facades\Storage; // Importamos el facade Storage
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GaleriaEventoController extends Controller
{
    // Listar imágenes de galería para un evento específico
    public function indexForEvento(string $eventoId)
    {
        if (!Evento::find($eventoId)) {
            return response()->json(['message' => 'Evento no encontrado'], 404);
        }
        $imagenes = GaleriaEvento::where('id_evento', $eventoId)->get();
        return response()->json($imagenes);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_evento' => 'required|exists:eventos,id',
            'imagen_file' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:10240', // Límite de 10MB
            'descripcion' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $data = $request->except(['imagen_file']); // Excluimos el archivo para no asignarlo directamente
        $data['url_imagen'] = $this->handleImageUpload($request, 'imagen_file'); // Guardamos el archivo y obtenemos la URL

        $imagenGaleria = GaleriaEvento::create($data);
        return response()->json($imagenGaleria, 201);
    }

    public function show(string $id)
    {
        $imagenGaleria = GaleriaEvento::find($id);
        if (is_null($imagenGaleria)) {
            return response()->json(['message' => 'Imagen de galería no encontrada'], 404);
        }
        return response()->json($imagenGaleria);
    }

    public function update(Request $request, string $id)
    {
        $imagenGaleria = GaleriaEvento::find($id);
        if (is_null($imagenGaleria)) {
            return response()->json(['message' => 'Imagen de galería no encontrada'], 404);
        }

        $validator = Validator::make($request->all(), [
            'id_evento' => 'sometimes|required|exists:eventos,id', // id_evento puede ser actualizado, pero no es común
            'imagen_file' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:10240', // Límite de 10MB
            'descripcion' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $data = $request->except(['imagen_file', '_method']); // Excluimos el archivo y el método HTTP

        // Manejamos la actualización del archivo de imagen
        if ($request->hasFile('imagen_file')) {
            $data['url_imagen'] = $this->handleImageUpload($request, 'imagen_file', $imagenGaleria->url_imagen);
        } else if (array_key_exists('url_imagen', $request->all()) && ($request->input('url_imagen') === null || $request->input('url_imagen') === '')) {
            // Si el frontend envía explícitamente url_imagen como null/cadena vacía, eliminamos el archivo antiguo
            $this->deleteImage($imagenGaleria->url_imagen);
            $data['url_imagen'] = null;
        }

        $imagenGaleria->update($data);

        return response()->json($imagenGaleria);
    }

    public function destroy(string $id)
    {
        $imagenGaleria = GaleriaEvento::find($id);
        if (is_null($imagenGaleria)) {
            return response()->json(['message' => 'Imagen de galería no encontrada'], 404);
        }

        // Eliminamos el archivo físico asociado
        if ($imagenGaleria->url_imagen) {
            $this->deleteImage($imagenGaleria->url_imagen);
        }

        $imagenGaleria->delete();
        return response()->json(null, 204);
    }

    /**
     * Método auxiliar para manejar la subida de imágenes.
     * Almacena el archivo y devuelve su URL pública.
     * Elimina el archivo antiguo si se proporciona.
     */
    private function handleImageUpload(Request $request, $fieldName, $oldImagePath = null)
    {
        if ($request->hasFile($fieldName)) {
            if ($oldImagePath) {
                $this->deleteImage($oldImagePath);
            }
            $path = $request->file($fieldName)->store('galeria_eventos', 'public'); // Almacenamos en la carpeta 'galeria_eventos'
            return Storage::url($path);
        }
        return $oldImagePath;
    }

    /**
     * Método auxiliar para eliminar un archivo de imagen del almacenamiento público.
     */
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
