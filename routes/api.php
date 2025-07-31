<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController; // Import the new controller

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Authentication routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum'); // Protect logout

// Rutas para Artistas
Route::apiResource('artistas', App\Http\Controllers\Api\ArtistaController::class);
// Esto crea automáticamente las siguientes rutas:
// GET /api/artistas -> ArtistaController@index
// POST /api/artistas -> ArtistaController@store
// GET /api/artistas/{artista} -> ArtistaController@show
// PUT/PATCH /api/artistas/{artista} -> ArtistaController@update
// DELETE /api/artistas/{artista} -> ArtistaController@destroy

// Rutas para Eventos
Route::apiResource('eventos', App\Http\Controllers\Api\EventoController::class);

// Rutas para Testimonios de Eventos
Route::get('eventos/{eventoId}/testimonios', [App\Http\Controllers\Api\TestimonioEventoController::class, 'indexForEvento']);
Route::post('testimonios-eventos', [App\Http\Controllers\Api\TestimonioEventoController::class, 'store']); // POST /api/testimonios-eventos
Route::get('testimonios-eventos/{id}', [App\Http\Controllers\Api\TestimonioEventoController::class, 'show']);
Route::put('testimonios-eventos/{id}', [App\Http\Controllers\Api\TestimonioEventoController::class, 'update'])->middleware('auth:sanctum');
Route::delete('testimonios-eventos/{id}', [App\Http\Controllers\Api\TestimonioEventoController::class, 'destroy'])->middleware('auth:sanctum');

// Rutas para Galerías de Eventos
Route::get('eventos/{eventoId}/galeria', [App\Http\Controllers\Api\GaleriaEventoController::class, 'indexForEvento']);
Route::apiResource('galerias-eventos', App\Http\Controllers\Api\GaleriaEventoController::class)->except(['index']); // 'index' se maneja arriba
