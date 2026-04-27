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
use App\Http\Controllers\Api\EncouragementShareController;
use App\Http\Controllers\Api\EncouragementGeneratorController;
use App\Http\Controllers\Api\AdminLanzamientoImportController;
use App\Http\Controllers\Api\AdminPostImportController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\ArtistaController;
use App\Http\Controllers\Api\MediaFileController;
use App\Http\Controllers\Api\AdminMediaController;
use App\Http\Controllers\Api\Shop\ShopCategoryController;
use App\Http\Controllers\Api\Shop\ShopCheckoutController;
use App\Http\Controllers\Api\Shop\ShopOrderController;
use App\Http\Controllers\Api\Shop\ShopProductController;
use App\Http\Controllers\Api\Shop\ShopProductTypeController;
use App\Http\Controllers\Api\Shop\ShopPromotionController;
use App\Http\Controllers\Api\Shop\ShopShippingController;

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
Route::post('encouragement/generate-verse', [EncouragementGeneratorController::class, 'generateVerse']);
Route::post('encouragement/generate-context', [EncouragementGeneratorController::class, 'generateContext']);
Route::post('encouragement/generate-prayer', [EncouragementGeneratorController::class, 'generatePrayer']);

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user()->load('socialAccounts', 'roles');
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
Route::apiResource('eventos', App\Http\Controllers\Api\EventoController::class)->only(['index', 'show']);

// Rutas para Lanzamientos
// Custom route for latest releases
Route::get('lanzamientos/latest', [App\Http\Controllers\Api\LanzamientoController::class, 'latest']);
Route::apiResource('lanzamientos', App\Http\Controllers\Api\LanzamientoController::class);

// Rutas para Productos (Entradas)
Route::apiResource('products', ProductController::class)->only(['index', 'show']);

Route::prefix('shop')->group(function () {
    Route::get('categories', [ShopCategoryController::class, 'index']);
    Route::get('categories/{id}', [ShopCategoryController::class, 'show']);
    Route::get('product-types', [ShopProductTypeController::class, 'index']);
    Route::get('product-types/{id}', [ShopProductTypeController::class, 'show']);
    Route::get('products', [ShopProductController::class, 'index']);
    Route::get('products/{id}', [ShopProductController::class, 'show']);
    Route::get('promotions', [ShopPromotionController::class, 'index']);
    Route::get('promotions/{id}', [ShopPromotionController::class, 'show']);
    Route::get('orders/{id}', [ShopOrderController::class, 'show']);
    Route::post('shipping/quote', [ShopShippingController::class, 'quote']);
    Route::post('checkout', [ShopCheckoutController::class, 'createPreference']);
    Route::post('mercadopago/webhook', [ShopCheckoutController::class, 'handleWebhook']);
});

// Checkout de Entradas (Mercado Pago)
Route::post('mercadopago/webhook', [TicketCheckoutController::class, 'handleWebhook']);

Route::post('ticket-checkout', [TicketCheckoutController::class, 'createPreference']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('ticket-orders', [TicketOrderController::class, 'index']);
    Route::get('ticket-orders/{id}', [TicketOrderController::class, 'show']);
    Route::post('encouragement/share-email', [EncouragementShareController::class, 'sendToAuthenticatedUser']);
    Route::post('sorteos/{sorteo}/participate', [SorteoController::class, 'participate']);
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
    return $request->user()->load('socialAccounts', 'roles');
});
Route::middleware('auth:sanctum')->put('/user', [AuthController::class, 'updateProfile']);

// Rutas para Hero Slides (Protegidas - si se necesitan)
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('hero-slides', HeroSlideController::class)->except(['index']);
    Route::post('prayer-requests', [PrayerRequestController::class, 'store']);
});

Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('admin/eventos/{eventoId}/testimonios', [App\Http\Controllers\Api\TestimonioEventoController::class, 'indexForEvento']);
    Route::apiResource('eventos', App\Http\Controllers\Api\EventoController::class)->only(['store', 'update', 'destroy']);
    Route::apiResource('products', ProductController::class)->only(['store', 'update', 'destroy']);
    Route::get('admin/shop/categories', [ShopCategoryController::class, 'index']);
    Route::apiResource('admin/shop/categories', ShopCategoryController::class)->only(['store', 'update', 'destroy']);
    Route::get('admin/shop/product-types', [ShopProductTypeController::class, 'index']);
    Route::apiResource('admin/shop/product-types', ShopProductTypeController::class)->only(['store', 'update', 'destroy']);
    Route::get('admin/shop/products', [ShopProductController::class, 'adminIndex']);
    Route::apiResource('admin/shop/products', ShopProductController::class)->only(['store', 'update', 'destroy']);
    Route::apiResource('admin/shop/promotions', ShopPromotionController::class)->only(['store', 'update', 'destroy']);
    Route::get('admin/shop/orders', [ShopOrderController::class, 'index']);
    Route::get('admin/shop/orders/{id}', [ShopOrderController::class, 'show']);
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
    Route::delete('admin/sorteos/{sorteo}/participants/{user}', [SorteoController::class, 'removeParticipant']);
    Route::get('admin/sorteos/{sorteo}/thanks-email-preview', [SorteoController::class, 'thankEmailPreview']);
    Route::post('admin/sorteos/{sorteo}/send-thanks-email', [SorteoController::class, 'sendThankEmail']);
    Route::get('admin/promo-video', [PromoVideoController::class, 'showAdmin']);
    Route::post('admin/promo-video', [PromoVideoController::class, 'update']);
    Route::get('admin/promo-inquiries', [PromoInquiryController::class, 'index']);
    Route::delete('admin/promo-inquiries/{promoInquiry}', [PromoInquiryController::class, 'destroy']);
    Route::get('admin/press-inquiries', [PressInquiryController::class, 'index']);
    Route::delete('admin/press-inquiries/{pressInquiry}', [PressInquiryController::class, 'destroy']);
    Route::get('admin/promo-emails', [AdminPromotionEmailController::class, 'index']);
    Route::post('admin/promo-emails/send', [AdminPromotionEmailController::class, 'send']);
    Route::post('admin/promo-emails/send-invitation', [AdminPromotionEmailController::class, 'sendInvitation']);
    Route::post('admin/promo-emails/send-buyers-notice', [AdminPromotionEmailController::class, 'sendBuyersNotice']);
    Route::post('admin/promo-emails/send-post-thanks', [AdminPromotionEmailController::class, 'sendPostThanks']);
    Route::get('admin/lanzamientos/import/candidates', [AdminLanzamientoImportController::class, 'candidates']);
    Route::post('admin/lanzamientos/import', [AdminLanzamientoImportController::class, 'import']);
    Route::get('admin/posts/import/candidates', [AdminPostImportController::class, 'candidates']);
    Route::get('admin/posts/generate-candidates', [AdminPostImportController::class, 'generate']);
    Route::post('admin/posts/import', [AdminPostImportController::class, 'import']);

    // Roles CRUD
    Route::get('admin/roles', [RoleController::class, 'index']);
    Route::post('admin/roles', [RoleController::class, 'store']);
    Route::put('admin/roles/{role}', [RoleController::class, 'update']);
    Route::delete('admin/roles/{role}', [RoleController::class, 'destroy']);

    // Asignación de roles a usuarios
    Route::get('admin/users/{user}/roles', [RoleController::class, 'userRoles']);
    Route::post('admin/users/{user}/roles', [RoleController::class, 'assignRole']);
    Route::delete('admin/users/{user}/roles/{role}', [RoleController::class, 'removeRole']);

    // Vinculación artista ↔ usuario
    Route::post('admin/users/{user}/artista', [UserController::class, 'assignArtista']);
    Route::delete('admin/users/{user}/artista', [UserController::class, 'removeArtista']);

    // Multimedia — vista admin
    Route::get('admin/media', [AdminMediaController::class, 'index']);
    Route::patch('admin/media/{mediaFile}/downloadable', [AdminMediaController::class, 'toggleDownloadable']);
    Route::delete('admin/media/{mediaFile}', [AdminMediaController::class, 'destroy']);
});

// Mi perfil de artista
Route::middleware('auth:sanctum')->get('me/artista', [ArtistaController::class, 'myArtista']);
Route::middleware('auth:sanctum')->post('me/artista', [ArtistaController::class, 'updateMyArtista']);

// Multimedia — categorías (público)
Route::get('media/categories', [MediaFileController::class, 'categories']);

// Multimedia — archivos de un artista (cliente, requiere auth)
Route::middleware('auth:sanctum')->get('media/artistas/{artista}', [MediaFileController::class, 'artistaFiles']);

// Multimedia — gestión propia del artista
Route::middleware('auth:sanctum')->group(function () {
    Route::get('media/files', [MediaFileController::class, 'index']);
    Route::post('media/files', [MediaFileController::class, 'store']);
    Route::put('media/files/{mediaFile}', [MediaFileController::class, 'update']);
    Route::delete('media/files/{mediaFile}', [MediaFileController::class, 'destroy']);
    Route::get('media/files/{mediaFile}/download', [MediaFileController::class, 'download']);
});
