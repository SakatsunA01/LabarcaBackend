<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Artista;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $users = User::withCount('prayerRequests')
            ->with(['roles', 'artista:id,user_id,name,imageUrl'])
            ->get();
        return response()->json($users);
    }

    // Vincular un artista a un usuario (admin)
    public function assignArtista(Request $request, User $user)
    {
        $request->validate(['artista_id' => 'required|exists:artistas,id']);

        $artista = Artista::findOrFail($request->artista_id);

        if ($artista->user_id && $artista->user_id !== $user->id) {
            return response()->json(['message' => 'Este artista ya está vinculado a otro usuario.'], 422);
        }

        // Desvincular artista anterior de este usuario
        Artista::where('user_id', $user->id)->update(['user_id' => null]);

        $artista->update(['user_id' => $user->id]);

        return response()->json($user->load(['roles', 'artista:id,user_id,name,imageUrl']));
    }

    // Desvincular artista de un usuario (admin)
    public function removeArtista(User $user)
    {
        Artista::where('user_id', $user->id)->update(['user_id' => null]);
        return response()->json($user->load(['roles', 'artista:id,user_id,name,imageUrl']));
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        // The User model already has the password and remember_token hidden.
        // We can add more relationships to load if needed, for example:
        // $user->load('prayerRequests');
        return response()->json($user);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $user = User::find($id);

        if (is_null($user)) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'admin_sn' => 'sometimes|required|boolean',
            // Add other fields that can be updated by an admin here
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        // Prevent admin from accidentally removing their own admin status
        if ($request->user()->id == $user->id && $request->has('admin_sn') && !$request->admin_sn) {
            return response()->json(['message' => 'You cannot remove your own admin status.'], 400);
        }

        $user->update($request->only(['admin_sn']));

        return response()->json($user);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $user = User::find($id);

        if (is_null($user)) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Prevent admin from deleting themselves
        if (auth()->user()->id == $id) {
            return response()->json(['message' => 'You cannot delete your own account.'], 400);
        }

        $user->delete();

        return response()->json(null, 204);
    }
}
