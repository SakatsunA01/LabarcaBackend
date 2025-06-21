<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GaleriaEvento;
use App\Models\Evento;
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
            'url_imagen' => 'required|string|max:255', // Podrías validar que sea una URL o manejar subida de archivos
            'descripcion' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        // Si manejas subida de archivos, aquí iría la lógica para guardar el archivo
        // y obtener la URL. Por ahora, asumimos que la URL se envía directamente.

        $imagenGaleria = GaleriaEvento::create($request->all());
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
            'url_imagen' => 'sometimes|required|string|max:255',
            'descripcion' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $imagenGaleria->update($request->all());
        return response()->json($imagenGaleria);
    }

    public function destroy(string $id)
    {
        $imagenGaleria = GaleriaEvento::find($id);
        if (is_null($imagenGaleria)) {
            return response()->json(['message' => 'Imagen de galería no encontrada'], 404);
        }
        // Opcional: eliminar el archivo físico si lo estás almacenando
        $imagenGaleria->delete();
        return response()->json(null, 204);
    }
}
