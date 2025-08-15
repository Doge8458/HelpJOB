<?php
// pages/test_delete.php
// Herramienta de depuración para verificar la sesión y los permisos de borrado.

header('Content-Type: text/html; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre style='background-color: #282c34; color: #abb2bf; font-family: Consolas, monospace; padding: 20px; border-radius: 8px; white-space: pre-wrap; word-wrap: break-word;'>";

echo "<b>--- INICIANDO DEPURACIÓN DE PERMISOS DE BORRADO ---</b>\n\n";

// 1. Incluir dependencias
echo "<b>Paso 1: Cargando archivos de conexión y funciones...</b>\n";
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
echo "<span style='color: #98c379;'>Archivos cargados con éxito.</span>\n\n";

// 2. Verificar si hay una sesión activa
echo "<b>Paso 2: Verificando la sesión de usuario...</b>\n";
if (!isLoggedIn()) {
    die("<span style='color: #e06c75;'>ERROR: No hay una sesión activa. Por favor, inicia sesión como empleador/empresa primero y luego vuelve a esta página.</span>\n");
}
echo "<span style='color: #98c379;'>Sesión encontrada.</span>\n";
echo "ID de Usuario en Sesión: <b style='color: #61afef;'>" . htmlspecialchars(getLoggedInUserId()) . "</b>\n";
echo "Tipo de Usuario en Sesión: <b style='color: #61afef;'>" . htmlspecialchars(getLoggedInUserType()) . "</b>\n\n";

// 3. Simular la comprobación de la API
echo "<b>Paso 3: Simulando la comprobación de permisos que hace la API...</b>\n";
$user_type = getLoggedInUserType();
$is_allowed = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isLoggedIn() && ($user_type === 'employer' || $user_type === 'company')) {
    $is_allowed = true;
}

if ($is_allowed) {
    echo "<span style='color: #98c379;'>¡ÉXITO! Los permisos son correctos. El usuario SÍ tiene permiso para intentar eliminar una publicación.</span>\n";
    echo "Si el borrado aún falla en la aplicación, el problema podría ser que el ID del empleo no coincide con el ID del empleador en la base de datos.\n";
} else {
    echo "<span style='color: #e06c75;'>¡FALLO! El usuario NO tiene los permisos necesarios.</span>\n";
    echo "Detalles:\n";
    echo " - Método de Petición: " . $_SERVER['REQUEST_METHOD'] . " (Debería ser POST)\n";
    echo " - Sesión iniciada: " . (isLoggedIn() ? 'Sí' : 'No') . "\n";
    echo " - Tipo de usuario: " . htmlspecialchars($user_type) . " (Debería ser 'employer' o 'company')\n";
}

echo "\n\n<b>--- Fin de la depuración ---</b>";
echo "</pre>";
?>

