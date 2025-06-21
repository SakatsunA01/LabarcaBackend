<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TestimonioEvento;
use App\Models\Evento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class TestimonioEventoController extends Controller
{
    // Listar testimonios para un evento específico
    public function indexForEvento(string $eventoId)
    {
        if (!Evento::find($eventoId)) {
            return response()->json(['message' => 'Evento no encontrado'], 404);
        }
        $testimonios = TestimonioEvento::where('id_evento', $eventoId)->with('usuario')->get();
        return response()->json($testimonios);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_evento' => 'required|exists:eventos,id',
            'comentario' => 'required|string',
            'nombre_usuario' => 'nullable|string|max:255', // Para usuarios no logueados
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $data = $request->all();
        if (Auth::check()) { // Si el usuario está autenticado (Sanctum)
            $data['usuario_id'] = Auth::id();
            $data['nombre_usuario'] = Auth::user()->name; // Opcional, si quieres guardar el nombre del usuario logueado
        }

        $testimonio = TestimonioEvento::create($data);
        return response()->json($testimonio, 201);
    }

    public function show(string $id)
    {
        $testimonio = TestimonioEvento::with('usuario')->find($id);
        if (is_null($testimonio)) {
            return response()->json(['message' => 'Testimonio no encontrado'], 404);
        }
        return response()->json($testimonio);
    }

    public function update(Request $request, string $id)
    {
        $testimonio = TestimonioEvento::find($id);
        if (is_null($testimonio)) {
            return response()->json(['message' => 'Testimonio no encontrado'], 404);
        }

        // Aquí podrías añadir lógica de autorización, ej: solo el usuario que lo creó o un admin puede editar

        $validator = Validator::make($request->all(), [
            'comentario' => 'sometimes|required|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $testimonio->update($request->only('comentario'));
        return response()->json($testimonio);
    }

    public function destroy(string $id)
    {
        $testimonio = TestimonioEvento::find($id);
        if (is_null($testimonio)) {
            return response()->json(['message' => 'Testimonio no encontrado'], 404);
        }

        // Aquí podrías añadir lógica de autorización

        $testimonio->delete();
        return response()->json(null, 204);
    }
}
