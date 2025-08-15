<?php
// pages/test_single_job.php
// Herramienta de depuración para verificar la obtención de un solo empleo.

header('Content-Type: text/html; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre style='background-color: #282c34; color: #abb2bf; font-family: Consolas, monospace; padding: 20px; border-radius: 8px; white-space: pre-wrap; word-wrap: break-word;'>";

// 1. Incluir dependencias
echo "<b>Paso 1: Cargando archivos de conexión y funciones...</b>\n";
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
echo "<span style='color: #98c379;'>Archivos cargados con éxito.</span>\n\n";

// 2. Verificar el ID del post
echo "<b>Paso 2: Verificando el ID del empleo desde la URL (ej: ?post_id=8)...</b>\n";
if (!isset($_GET['post_id']) || !is_numeric($_GET['post_id'])) {
    die("<span style='color: #e06c75;'>ERROR: Por favor, proporciona un ID de empleo válido en la URL. Ejemplo: test_single_job.php?post_id=8</span>\n");
}
$post_id = (int)$_GET['post_id'];
echo "ID de empleo a buscar: <span style='color: #61afef;'>" . $post_id . "</span>\n\n";

// 3. Preparar la consulta SQL (la misma que usa la API)
echo "<b>Paso 3: Preparando la consulta SQL para obtener los detalles...</b>\n";
$sql = "SELECT p.*, u.full_name as employer_full_name, u.profile_image_path as employer_profile_image, u.company_name as employer_company_name 
        FROM JobPostings p 
        JOIN Users u ON p.employer_id = u.user_id 
        WHERE p.post_id = ?";
echo "Consulta SQL: \n<span style='color: #c678dd;'>" . htmlspecialchars($sql) . "</span>\n\n";

// 4. Ejecutar la consulta
echo "<b>Paso 4: Ejecutando la consulta en la base de datos...</b>\n";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("<span style='color: #e06c75;'>ERROR DE PREPARACIÓN DE SQL: " . htmlspecialchars($conn->error) . "</span>\n");
}

$stmt->bind_param("i", $post_id);
$stmt->execute();
$result = $stmt->get_result();
echo "<span style='color: #98c379;'>Consulta ejecutada.</span>\n\n";

// 5. Mostrar el resultado
echo "<b>Paso 5: Analizando y mostrando el resultado...</b>\n";
if ($result->num_rows > 0) {
    echo "<span style='color: #98c379;'>¡ÉXITO! Se encontró el empleo. Aquí están los datos:</span>\n\n";
    $job = $result->fetch_assoc();
    // Usamos json_encode para una visualización bonita
    echo "<span style='color: #d19a66;'>" . json_encode($job, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</span>";
} else {
    echo "<span style='color: #e06c75;'>FALLO: La consulta se ejecutó, pero no se encontró ningún empleo con el ID " . $post_id . ". Verifica que el ID exista en tu tabla 'jobpostings'.</span>\n";
}

$stmt->close();
$conn->close();

echo "\n\n<b>--- Fin de la depuración ---</b>";
echo "</pre>";
?>
