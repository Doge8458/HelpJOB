<?php
// Establecer la cabecera para mostrar JSON de forma legible
header('Content-Type: application/json; charset=utf-8');

// Incluir los archivos necesarios
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

echo "--- Iniciando Depuración de API de Empleos --- \n\n";

// Verificar si el usuario está logueado (necesario para la API real)
if (!isLoggedIn()) {
    $response = ['success' => false, 'message' => 'Error de Depuración: No has iniciado sesión. Accede primero como empleador o aspirante.'];
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit();
}

echo "--- Sesión de Usuario Verificada --- \n";
echo "ID de Usuario: " . getLoggedInUserId() . "\n";
echo "Tipo de Usuario: " . getLoggedInUserType() . "\n\n";

// --- Prueba 1: API para listar todos los empleos (para aspirantes) ---
echo "--- Probando api/jobs.php?action=list --- \n";

$sql_list = "SELECT * FROM JobPostings WHERE post_status = 'active'";
$stmt_list = $conn->prepare($sql_list);

if ($stmt_list === false) {
    $response = ['success' => false, 'message' => 'Error de SQL en la preparación de la consulta (list): ' . $conn->error];
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit();
}

$stmt_list->execute();
$result_list = $stmt_list->get_result();
$jobs_list = $result_list->fetch_all(MYSQLI_ASSOC);

$response_list = ['success' => true, 'action' => 'list', 'count' => count($jobs_list), 'jobs' => $jobs_list];
echo json_encode($response_list, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "\n\n";
$stmt_list->close();


// --- Prueba 2: API para listar empleos por empleador (para el dashboard) ---
echo "--- Probando api/jobs.php?action=list_by_employer --- \n";
$employer_id = getLoggedInUserId();

$sql_employer = "SELECT * FROM JobPostings WHERE employer_id = ?";
$stmt_employer = $conn->prepare($sql_employer);

if ($stmt_employer === false) {
    $response = ['success' => false, 'message' => 'Error de SQL en la preparación de la consulta (list_by_employer): ' . $conn->error];
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit();
}

$stmt_employer->bind_param("i", $employer_id);
$stmt_employer->execute();
$result_employer = $stmt_employer->get_result();
$jobs_employer = $result_employer->fetch_all(MYSQLI_ASSOC);

$response_employer = ['success' => true, 'action' => 'list_by_employer', 'count' => count($jobs_employer), 'jobs' => $jobs_employer];
echo json_encode($response_employer, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
$stmt_employer->close();

$conn->close();
?>
