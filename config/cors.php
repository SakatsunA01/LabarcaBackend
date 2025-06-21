<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'], // Puedes ser más específico si lo deseas, ej: ['GET', 'POST', 'PUT', 'DELETE']

    'allowed_origins' => [
        env('FRONTEND_URL', 'http://localhost:5173'), // Añade la URL de tu frontend Vue
        // Puedes añadir más orígenes si es necesario
        // 'http://tu-dominio-de-produccion.com'
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true, // ¡Muy importante! Cambia esto a true

];
