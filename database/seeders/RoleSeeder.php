<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['slug' => 'usuario',          'display_name' => 'Usuario',            'description' => 'Rol base, asignado a todos por defecto.'],
            ['slug' => 'cliente',           'display_name' => 'Cliente',            'description' => 'Puede ver y descargar multimedia (según permisos).'],
            ['slug' => 'artista',           'display_name' => 'Artista',            'description' => 'Gestiona su propio perfil y su multimedia.'],
            ['slug' => 'colaborador',       'display_name' => 'Colaborador',        'description' => 'Puede editar perfiles de artistas y gestionar multimedia.'],
            ['slug' => 'de_la_casa',        'display_name' => 'De la casa',         'description' => 'Acceso editorial amplio: artistas, lanzamientos, posts, galería.'],
            ['slug' => 'moderador',         'display_name' => 'Moderador',          'description' => 'Modera testimonios, peticiones de oración y prensa.'],
            ['slug' => 'gestor_contenido',  'display_name' => 'Gestor de contenido','description' => 'Gestiona artistas, lanzamientos, posts, categorías y hero.'],
            ['slug' => 'gestor_eventos',    'display_name' => 'Gestor de eventos',  'description' => 'Gestiona eventos, tickets y sorteos.'],
            ['slug' => 'gestor_tienda',     'display_name' => 'Gestor de tienda',   'description' => 'Gestiona productos, tienda y pedidos.'],
            ['slug' => 'configuraciones',   'display_name' => 'Configuraciones',    'description' => 'Acceso al panel admin sin secciones específicas.'],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['slug' => $role['slug']], $role);
        }

        // Asignar rol "usuario" a todos los existentes que no lo tengan
        $usuarioRole = Role::where('slug', 'usuario')->first();
        User::chunk(100, function ($users) use ($usuarioRole) {
            foreach ($users as $user) {
                if (!$user->roles->contains('id', $usuarioRole->id)) {
                    $user->roles()->attach($usuarioRole->id, ['granted_by' => null]);
                }
            }
        });
    }
}
