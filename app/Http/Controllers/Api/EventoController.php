<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Evento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EventoController extends Controller
{
    public function index()
    {
        // Ordenar por fecha más próxima primero
        $eventos = Evento::orderBy('fecha', 'asc')->get();
        return response()->json($eventos);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'fecha' => 'required|date',
            'link_compra' => 'nullable|url|max:255',
            'descripcion' => 'nullable|string',
            'lugar' => 'nullable|string|max:255',
            'imagenUrl' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $evento = Evento::create($request->all());
        return response()->json($evento, 201);
    }

    public function show(string $id)
    {
        $evento = Evento::with(['testimonios', 'galeria'])->find($id); // Cargar relaciones
        if (is_null($evento)) {
            return response()->json(['message' => 'Evento no encontrado'], 404);
        }
        return response()->json($evento);
    }

    public function update(Request $request, string $id)
    {
        $evento = Evento::find($id);
        if (is_null($evento)) {
            return response()->json(['message' => 'Evento no encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'nombre' => 'sometimes|required|string|max:255',
            'fecha' => 'sometimes|required|date',
            'link_compra' => 'nullable|url|max:255',
            'descripcion' => 'nullable|string',
            'lugar' => 'nullable|string|max:255',
            'imagenUrl' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $evento->update($request->all());
        return response()->json($evento);
    }

    public function destroy(string $id)
    {
        $evento = Evento::find($id);
        if (is_null($evento)) {
            return response()->json(['message' => 'Evento no encontrado'], 404);
        }
        // Opcional: Eliminar testimonios y galería relacionados si no se usa ON DELETE CASCADE
        // $evento->testimonios()->delete();
        // $evento->galeria()->delete();
        $evento->delete();
        return response()->json(null, 204);
    }
}
