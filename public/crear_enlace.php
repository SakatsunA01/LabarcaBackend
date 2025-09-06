<?php

// El objetivo es la carpeta de almacenamiento real
$target = '../storage/app/public';

// El nombre del enlace que quieres crear dentro de la carpeta public
$link = 'storage';

// Elimina el enlace si ya existe (para evitar errores)
if (file_exists($link)) {
    unlink($link);
}

// Crea el nuevo enlace simbólico
if (symlink($target, $link)) {
    echo "¡Enlace simbólico creado con éxito!";
} else {
    echo "Error: No se pudo crear el enlace simbólico.";
}