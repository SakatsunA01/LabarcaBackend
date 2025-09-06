<?php
echo "<h1>Diagnóstico de Enlace Simbólico</h1>";
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Paso 1: Verificar si la función symlink está habilitada
if (!function_exists('symlink')) {
    die("<p style='color:red; font-weight:bold;'>Error Crítico: La función <code>symlink()</code> está deshabilitada en la configuración de PHP de este servidor. No se puede continuar.</p>");
}
echo "<p style='color:green;'>Paso 1: La función <code>symlink()</code> SÍ está disponible. ¡Bien!</p>";

// Definir rutas
$target = __DIR__ . '/../storage/app/public';
$link = __DIR__ . '/storage';

echo "<p><b>Ruta Objetivo (Target):</b> " . $target . "</p>";
echo "<p><b>Ruta del Enlace (Link):</b> " . $link . "</p>";

// Paso 2: Verificar si el directorio objetivo existe
if (!is_dir($target)) {
    die("<p style='color:red; font-weight:bold;'>Error: El directorio objetivo no existe en la ruta esperada. Asegúrate de que la carpeta <code>storage/app/public</code> exista.</p>");
}
echo "<p style='color:green;'>Paso 2: El directorio objetivo <code>storage/app/public</code> existe.</p>";

// Paso 3: Verificar si ya existe algo llamado 'storage' en la carpeta public
if (file_exists($link) || is_link($link)) {
    echo "<p>Paso 3: Ya existe un archivo o enlace llamado <code>storage</code>. Se intentará eliminar...</p>";
    if (is_link($link)) {
        unlink($link);
    } elseif (is_dir($link)) {
        // Cuidado: esto solo funciona si el directorio está vacío
        rmdir($link);
    }
    echo "<p style='color:orange;'>Se eliminó el archivo/enlace/directorio 'storage' previo.</p>";
} else {
    echo "<p style='color:green;'>Paso 3: No existe un enlace previo llamado <code>storage</code>.</p>";
}

// Paso 4: Intentar crear el enlace
echo "<p>Paso 4: Intentando crear el enlace simbólico...</p>";
if (symlink($target, $link)) {
    echo "<h2 style='color:blue;'>¡Éxito! El enlace simbólico fue creado correctamente.</h2>";
} else {
    echo "<h2 style='color:red;'>Error Final: No se pudo crear el enlace simbólico. Generalmente esto se debe a un problema de permisos en el servidor.</h2>";
}

echo "<h3>Diagnóstico finalizado. ¡No olvides borrar este archivo del servidor!</h3>";
?>