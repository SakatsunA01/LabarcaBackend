<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TestimonioEvento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class TestimonioEventoController extends Controller
{
    /**
     * Display a listing of the resource for a specific event.
     */
    public function indexForEvento(Request $request, $eventoId)
    {
        $includePending = $request->boolean('include_pending');

        if ($includePending && (!Auth::check() || !Auth::user()->admin_sn)) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $query = TestimonioEvento::where('id_evento', $eventoId)->with('usuario');
        if (!$includePending) {
            $query->where('approved', true);
        }

        return response()->json($query->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'id_evento' => 'required|exists:eventos,id',
            'comentario' => 'required|string',
            'foto' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
        ]);

        $user = Auth::user();

        $fotoPath = null;
        if ($request->hasFile('foto')) {
            $fotoPath = $request->file('foto')->store('testimonios', 'public');
        }

        $testimonio = TestimonioEvento::create([
            'id_evento' => $request->id_evento,
            'comentario' => $request->comentario,
            'usuario_id' => $user->id,
            'nombre_usuario' => $request->nombre_usuario ?? $user->name,
            'approved' => false,
            'foto_path' => $fotoPath,
        ]);

        return response()->json($testimonio, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $testimonio = TestimonioEvento::with('usuario')->find($id);
        if (!$testimonio) {
            return response()->json(['message' => 'Testimonio no encontrado'], 404);
        }
        return response()->json($testimonio);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $testimonio = TestimonioEvento::find($id);

        if (!$testimonio) {
            return response()->json(['message' => 'Testimonio no encontrado'], 404);
        }

        // Optional: Check if the user is authorized to update the testimony
        if ($testimonio->usuario_id !== Auth::id()) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $request->validate([
            'comentario' => 'required|string',
        ]);

        $testimonio->update($request->only('comentario'));

        return response()->json($testimonio);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $testimonio = TestimonioEvento::find($id);

        if (!$testimonio) {
            return response()->json(['message' => 'Testimonio no encontrado'], 404);
        }

        // Optional: Check if the user is authorized to delete the testimony
        if ($testimonio->usuario_id !== Auth::id()) {
            if (!Auth::check() || !Auth::user()->admin_sn) {
                return response()->json(['message' => 'No autorizado'], 403);
            }
        }

        if ($testimonio->foto_path) {
            Storage::disk('public')->delete($testimonio->foto_path);
        }

        $testimonio->delete();

        return response()->json(null, 204);
    }
}
