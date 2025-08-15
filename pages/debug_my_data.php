<?php
// pages/debug_my_data.php
// Herramienta para verificar qué aplicaciones y empleos guardados tiene un usuario en la DB.

header('Content-Type: text/html; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre style='background-color: #1e1e1e; color: #d4d4d4; font-family: Consolas, monospace; padding: 20px; border-radius: 8px; white-space: pre-wrap; word-wrap: break-word; font-size: 14px;'>";

echo "<h1 style='color: #569cd6; font-size: 20px;'>--- Depurador de Datos del Aspirante ---</h1>\n\n";

// 1. Incluir dependencias
echo "<b>Paso 1: Cargando archivos de conexión y funciones...</b>\n";
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
echo "<span style='color: #4ec9b0;'>Archivos cargados con éxito.</span>\n\n";

// 2. Verificar si hay una sesión activa
echo "<b>Paso 2: Verificando la sesión de usuario...</b>\n";
if (!isLoggedIn() || getLoggedInUserType() !== 'applicant') {
    die("<span style='color: #f44747;'>ERROR: Debes iniciar sesión como 'aspirante' para ver esta página.</span>\n");
}

$user_id = getLoggedInUserId();
echo "<span style='color: #4ec9b0;'>Sesión de aspirante encontrada.</span>\n";
echo "ID de Usuario en Sesión: <b style='color: #9cdcfe;'>" . htmlspecialchars($user_id) . "</b>\n\n";

// 3. Obtener las APLICACIONES desde la base de datos
echo "<b>Paso 3: Consultando la tabla 'Applications' para el usuario ID " . htmlspecialchars($user_id) . "...</b>\n";

$sql_apps = "SELECT a.application_id, a.applicant_id, a.post_id, a.application_date, a.application_status, p.title 
             FROM Applications a 
             JOIN JobPostings p ON a.post_id = p.post_id
             WHERE a.applicant_id = ?";
$stmt_apps = $conn->prepare($sql_apps);
$stmt_apps->bind_param("i", $user_id);
$stmt_apps->execute();
$result_apps = $stmt_apps->get_result();
$applications = $result_apps->fetch_all(MYSQLI_ASSOC);
$stmt_apps->close();

echo "Se encontraron <b style='color: #9cdcfe;'>" . count($applications) . "</b> aplicaciones.\n";
echo "<div style='background-color: #252526; padding: 15px; border-radius: 5px; margin-top: 10px;'>";
echo "<h2 style='color: #d7ba7d;'>Datos de Aplicaciones:</h2>";
if (!empty($applications)) {
    echo json_encode($applications, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    echo "No hay registros en la tabla 'Applications' para este usuario.";
}
echo "</div>\n\n";


// 4. Obtener los EMPLEOS GUARDADOS desde la base de datos
echo "<b>Paso 4: Consultando la tabla 'SavedPosts' para el usuario ID " . htmlspecialchars($user_id) . "...</b>\n";

$sql_saved = "SELECT s.saved_id, s.applicant_id, s.post_id, s.save_date, p.title
              FROM SavedPosts s
              JOIN JobPostings p ON s.post_id = p.post_id
              WHERE s.applicant_id = ?";
$stmt_saved = $conn->prepare($sql_saved);
$stmt_saved->bind_param("i", $user_id);
$stmt_saved->execute();
$result_saved = $stmt_saved->get_result();
$saved_posts = $result_saved->fetch_all(MYSQLI_ASSOC);
$stmt_saved->close();

echo "Se encontraron <b style='color: #9cdcfe;'>" . count($saved_posts) . "</b> empleos guardados.\n";
echo "<div style='background-color: #252526; padding: 15px; border-radius: 5px; margin-top: 10px;'>";
echo "<h2 style='color: #d7ba7d;'>Datos de Empleos Guardados:</h2>";
if (!empty($saved_posts)) {
    echo json_encode($saved_posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    echo "No hay registros en la tabla 'SavedPosts' para este usuario.";
}
echo "</div>\n\n";


$conn->close();
echo "<h1 style='color: #569cd6;'>--- Fin de la depuración ---</h1>";
echo "</pre>";
?>
