<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController; // Import the new controller
use App\Http\Controllers\Api\LikeController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\HeroSlideController;
use App\Http\Controllers\Api\PrayerRequestController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\TicketCheckoutController;
use App\Http\Controllers\Api\TicketOrderController;
use App\Http\Controllers\Api\AdminTicketOrderController;
use App\Http\Controllers\Api\SorteoController;
use App\Http\Controllers\Api\TicketVerificationController;
use App\Http\Controllers\Api\SocialAuthController;
use App\Http\Controllers\Api\PromoVideoController;
use App\Http\Controllers\Api\PromoInquiryController;
use App\Http\Controllers\Api\ArtistCategoryController;
use App\Http\Controllers\Api\PressInquiryController;
use App\Http\Controllers\Api\AdminPromotionEmailController;
use App\Http\Controllers\Api\PasswordResetController;

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

// Public routes
Route::get('hero-slides', [HeroSlideController::class, 'index']);
Route::get('prayer-requests', [PrayerRequestController::class, 'index']);
Route::get('sorteos', [SorteoController::class, 'publicIndex']);
Route::get('promo-video', [PromoVideoController::class, 'showPublic']);
Route::get('promo-video/stream', [PromoVideoController::class, 'stream']);
Route::post('promo-inquiries', [PromoInquiryController::class, 'store']);
Route::post('press-inquiries', [PressInquiryController::class, 'store']);

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user()->load('socialAccounts');
});
Route::middleware('auth:sanctum')->put('/user', [AuthController::class, 'updateProfile']);

// Authentication routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/auth/google', [SocialAuthController::class, 'google']);
Route::post('/auth/google/link', [SocialAuthController::class, 'linkGoogle'])->middleware('auth:sanctum');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum'); // Protect logout
Route::post('/password/request-code', [PasswordResetController::class, 'requestCode']);
Route::post('/password/reset-with-code', [PasswordResetController::class, 'resetWithCode']);

// Rutas para Artistas
Route::apiResource('artistas', App\Http\Controllers\Api\ArtistaController::class);
Route::apiResource('artist-categories', ArtistCategoryController::class);
// Esto crea automáticamente las siguientes rutas:
// GET /api/artistas -> ArtistaController@index
// POST /api/artistas -> ArtistaController@store
// GET /api/artistas/{artista} -> ArtistaController@show
// PUT/PATCH /api/artistas/{artista} -> ArtistaController@update
// DELETE /api/artistas/{artista} -> ArtistaController@destroy

// Rutas para Eventos
Route::apiResource('eventos', App\Http\Controllers\Api\EventoController::class);

// Rutas para Lanzamientos
// Custom route for latest releases
Route::get('lanzamientos/latest', [App\Http\Controllers\Api\LanzamientoController::class, 'latest']);
Route::apiResource('lanzamientos', App\Http\Controllers\Api\LanzamientoController::class);

// Rutas para Productos (Entradas)
Route::apiResource('products', ProductController::class);

// Checkout de Entradas (Mercado Pago)
Route::post('mercadopago/webhook', [TicketCheckoutController::class, 'handleWebhook']);

Route::post('ticket-checkout', [TicketCheckoutController::class, 'createPreference']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('ticket-orders', [TicketOrderController::class, 'index']);
    Route::get('ticket-orders/{id}', [TicketOrderController::class, 'show']);
});

// Rutas para Testimonios de Eventos
Route::get('eventos/{eventoId}/testimonios', [App\Http\Controllers\Api\TestimonioEventoController::class, 'indexForEvento']);
Route::post('testimonios-eventos', [App\Http\Controllers\Api\TestimonioEventoController::class, 'store'])->middleware('auth:sanctum'); // POST /api/testimonios-eventos
Route::get('testimonios-eventos/{id}', [App\Http\Controllers\Api\TestimonioEventoController::class, 'show']);
Route::put('testimonios-eventos/{id}', [App\Http\Controllers\Api\TestimonioEventoController::class, 'update'])->middleware('auth:sanctum');
Route::delete('testimonios-eventos/{id}', [App\Http\Controllers\Api\TestimonioEventoController::class, 'destroy'])->middleware('auth:sanctum');

// Rutas para Galerías de Eventos
Route::get('eventos/{eventoId}/galeria', [App\Http\Controllers\Api\GaleriaEventoController::class, 'indexForEvento']);
Route::apiResource('galerias-eventos', App\Http\Controllers\Api\GaleriaEventoController::class)->except(['index']); // 'index' se maneja arriba

// Rutas para Posts (Historias de la Comunidad)
Route::get('posts/latest', [App\Http\Controllers\Api\PostController::class, 'latest']);
Route::apiResource('posts', App\Http\Controllers\Api\PostController::class);

// Rutas para Categorías
Route::apiResource('categories', App\Http\Controllers\Api\CategoryController::class);

// Rutas para Likes
Route::post('posts/{post}/like', [LikeController::class, 'store']);
Route::delete('posts/{post}/like', [LikeController::class, 'destroy']);

// Rutas para Comentarios
Route::post('posts/{post}/comments', [CommentController::class, 'store']);
Route::delete('comments/{comment}', [CommentController::class, 'destroy']);

// Rutas para Hero Slides
// Rutas para Hero Slides (Públicas)
Route::get('hero-slides', [HeroSlideController::class, 'index']);
Route::get('prayer-requests', [PrayerRequestController::class, 'index']);

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user()->load('socialAccounts');
});
Route::middleware('auth:sanctum')->put('/user', [AuthController::class, 'updateProfile']);

// Rutas para Hero Slides (Protegidas - si se necesitan)
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('hero-slides', HeroSlideController::class)->except(['index']);
    Route::post('prayer-requests', [PrayerRequestController::class, 'store']);
});

Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('admin/prayer-requests', [PrayerRequestController::class, 'indexAdmin']);
    Route::put('admin/prayer-requests/{id}', [PrayerRequestController::class, 'update']);
    Route::delete('admin/prayer-requests/{id}', [PrayerRequestController::class, 'destroy']);
    Route::apiResource('admin/users', UserController::class)->except(['show', 'store']);
    Route::get('admin/ticket-orders', [AdminTicketOrderController::class, 'index']);
    Route::get('admin/ticket-orders/{id}', [AdminTicketOrderController::class, 'show']);
    Route::post('admin/ticket-orders/{id}/approve-cash', [AdminTicketOrderController::class, 'approveCash']);
    Route::post('admin/ticket-orders/{id}/reject-cash', [AdminTicketOrderController::class, 'rejectCash']);
    Route::post('admin/ticket-orders/{id}/send-email', [AdminTicketOrderController::class, 'sendTicketEmail']);
    Route::post('admin/ticket-orders/{id}/send-pending-email', [AdminTicketOrderController::class, 'sendPendingEmail']);
    Route::post('admin/ticket-orders/send-pending-bulk', [AdminTicketOrderController::class, 'sendPendingBulk']);
    Route::post('admin/ticket-orders/verify', [TicketVerificationController::class, 'verify']);
    Route::get('admin/sorteos', [SorteoController::class, 'index']);
    Route::post('admin/sorteos', [SorteoController::class, 'store']);
    Route::put('admin/sorteos/{sorteo}', [SorteoController::class, 'update']);
    Route::delete('admin/sorteos/{sorteo}', [SorteoController::class, 'destroy']);
    Route::get('admin/sorteos/{sorteo}/users', [SorteoController::class, 'users']);
    Route::post('admin/sorteos/{sorteo}/participants', [SorteoController::class, 'addParticipants']);
    Route::post('admin/sorteos/{sorteo}/close', [SorteoController::class, 'close']);
    Route::get('admin/promo-video', [PromoVideoController::class, 'showAdmin']);
    Route::post('admin/promo-video', [PromoVideoController::class, 'update']);
    Route::get('admin/promo-inquiries', [PromoInquiryController::class, 'index']);
    Route::delete('admin/promo-inquiries/{promoInquiry}', [PromoInquiryController::class, 'destroy']);
    Route::get('admin/press-inquiries', [PressInquiryController::class, 'index']);
    Route::delete('admin/press-inquiries/{pressInquiry}', [PressInquiryController::class, 'destroy']);
    Route::get('admin/promo-emails', [AdminPromotionEmailController::class, 'index']);
    Route::post('admin/promo-emails/send', [AdminPromotionEmailController::class, 'send']);
    Route::post('admin/promo-emails/send-invitation', [AdminPromotionEmailController::class, 'sendInvitation']);
});
