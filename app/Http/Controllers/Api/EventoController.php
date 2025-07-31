<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Evento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class EventoController extends Controller
{
    /**
     * Muestra una lista de todos los eventos, ordenados por fecha.
     */
    public function index()
    {
        // Ordenar por fecha más próxima primero
        $eventos = Evento::orderBy('fecha', 'asc')->get();
        return response()->json($eventos);
    }

    /**
     * Almacena un nuevo evento en la base de datos.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->validationRules());

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $data = $request->except(['imagenUrl']);
        if ($request->hasFile('imagenUrl')) {
            $data['imagenUrl'] = $this->handleImageUpload($request, 'imagenUrl');
        }

        $evento = Evento::create($data);
        return response()->json($evento, 201);
    }

    /**
     * Muestra un evento específico con sus relaciones.
     */
    public function show(string $id)
    {
        $evento = Evento::with(['testimonios', 'galeria'])->find($id); // Cargar relaciones
        if (is_null($evento)) {
            return response()->json(['message' => 'Evento no encontrado'], 404);
        }
        return response()->json($evento);
    }

    /**
     * Actualiza un evento existente en la base de datos.
     */
    public function update(Request $request, string $id)
    {
        $evento = Evento::find($id);
        if (is_null($evento)) {
            return response()->json(['message' => 'Evento no encontrado'], 404);
        }

        $validator = Validator::make($request->all(), $this->validationRules(true));

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $data = $request->except(['imagenUrl', '_method']);

        if ($request->hasFile('imagenUrl')) {
            $data['imagenUrl'] = $this->handleImageUpload($request, 'imagenUrl', $evento->imagenUrl);
        }

        $evento->update($data);
        return response()->json($evento);
    }

    /**
     * Elimina un evento de la base de datos.
     */
    public function destroy(string $id)
    {
        $evento = Evento::find($id);
        if (is_null($evento)) {
            return response()->json(['message' => 'Evento no encontrado'], 404);
        }

        // Eliminar la imagen asociada del almacenamiento
        if ($evento->imagenUrl) {
            $this->deleteImage($evento->imagenUrl);
        }

        // Opcional: Eliminar testimonios y galería relacionados si no se usa ON DELETE CASCADE
        // $evento->testimonios()->delete();
        // $evento->galeria()->delete();
        $evento->delete();
        return response()->json(null, 204);
    }

    /**
     * Define las reglas de validación para crear y actualizar eventos.
     */
    private function validationRules($isUpdate = false)
    {
        $sometimes = $isUpdate ? 'sometimes|' : '';
        return [
            'nombre' => $sometimes . 'required|string|max:255',
            'fecha' => $sometimes . 'required|date',
            'link_compra' => 'nullable|url|max:255',
            'descripcion' => 'nullable|string',
            'lugar' => 'nullable|string|max:255',
            'imagenUrl' => $sometimes . 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ];
    }

    /**
     * Maneja la subida de una imagen, la guarda y elimina la anterior si existe.
     */
    private function handleImageUpload(Request $request, $fieldName, $oldImagePath = null)
    {
        if ($request->hasFile($fieldName)) {
            // Eliminar la imagen anterior si existe
            if ($oldImagePath) {
                $this->deleteImage($oldImagePath);
            }

            $path = $request->file($fieldName)->store('eventos', 'public');
            return Storage::url($path);
        }

        // Si no se sube un archivo nuevo durante una actualización, se mantiene la ruta antigua.
        // Para la creación, esto devolverá null correctamente si no hay archivo.
        return $oldImagePath;
    }

    /**
     * Elimina un archivo de imagen del disco público.
     */
    private function deleteImage($imagePath)
    {
        if ($imagePath) {
            // Convierte la URL pública (/storage/...) a una ruta de almacenamiento (eventos/...)
            $path = str_replace('/storage', '', $imagePath);
            Storage::disk('public')->delete($path);
        }
    }
}
