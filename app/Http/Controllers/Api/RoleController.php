<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index()
    {
        return response()->json(Role::orderBy('display_name')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'slug'         => 'required|string|max:50|unique:roles,slug|regex:/^[a-z0-9_]+$/',
            'display_name' => 'required|string|max:100',
            'description'  => 'nullable|string|max:255',
        ]);

        return response()->json(Role::create($data), 201);
    }

    public function update(Request $request, Role $role)
    {
        $data = $request->validate([
            'display_name' => 'sometimes|required|string|max:100',
            'description'  => 'nullable|string|max:255',
        ]);

        $role->update($data);
        return response()->json($role);
    }

    public function destroy(Role $role)
    {
        $protectedSlugs = ['usuario', 'artista', 'colaborador', 'de_la_casa', 'cliente',
                           'moderador', 'gestor_contenido', 'gestor_eventos', 'gestor_tienda', 'configuraciones'];

        if (in_array($role->slug, $protectedSlugs)) {
            return response()->json(['message' => 'Este rol del sistema no puede eliminarse.'], 422);
        }

        $role->delete();
        return response()->json(null, 204);
    }

    // Roles de un usuario específico
    public function userRoles(User $user)
    {
        return response()->json($user->roles()->orderBy('display_name')->get());
    }

    // Asignar rol a usuario
    public function assignRole(Request $request, User $user)
    {
        $data = $request->validate(['role_id' => 'required|exists:roles,id']);

        if ($user->roles()->where('role_id', $data['role_id'])->exists()) {
            return response()->json(['message' => 'El usuario ya tiene ese rol.'], 422);
        }

        $user->roles()->attach($data['role_id'], ['granted_by' => $request->user()->id]);

        return response()->json($user->load('roles'));
    }

    // Quitar rol de usuario
    public function removeRole(Request $request, User $user, Role $role)
    {
        // No permitir quitarse el propio rol de admin
        if ($request->user()->id === $user->id && $role->slug === 'configuraciones') {
            return response()->json(['message' => 'No podés quitarte tu propio acceso de configuraciones.'], 422);
        }

        $user->roles()->detach($role->id);

        return response()->json($user->load('roles'));
    }
}
