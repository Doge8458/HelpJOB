<?php
// api/profile.php

require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Manejar solicitudes OPTIONS (preflight para CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Asegurar que el usuario esté logueado para acceder a su perfil
if (!isLoggedIn()) {
    sendJsonResponse(['success' => false, 'message' => 'Acceso denegado. Se requiere autenticación.'], 401);
}

$user_id = getLoggedInUserId();
$action = isset($_GET['action']) ? sanitizeInput($_GET['action']) : '';

switch ($action) {
    case 'get':
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            handleGetProfile($conn, $user_id);
        } else {
            sendJsonResponse(['success' => false, 'message' => 'Método no permitido para obtener perfil.'], 405);
        }
        break;
    case 'update':
        if ($_SERVER['REQUEST_METHOD'] === 'PUT') { // Usamos PUT para actualizar recursos
            handleUpdateProfile($conn, $user_id);
        } else {
            sendJsonResponse(['success' => false, 'message' => 'Método no permitido para actualizar perfil.'], 405);
        }
        break;
    default:
        sendJsonResponse(['success' => false, 'message' => 'Acción no reconocida.'], 400);
        break;
}

/**
 * Obtiene los datos del perfil del usuario logueado.
 * @param mysqli $conn Objeto de conexión.
 * @param int $user_id ID del usuario.
 */
function handleGetProfile($conn, $user_id) {
    $sql = "SELECT full_name, email, phone_number, location, user_type, profile_image_path, cv_pdf_path, bio, work_experience, skills, company_name, company_role
            FROM Users WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user_data = $result->fetch_assoc();
        sendJsonResponse(['success' => true, 'user_data' => $user_data]);
    } else {
        sendJsonResponse(['success' => false, 'message' => 'Perfil de usuario no encontrado.'], 404);
    }
    $stmt->close();
    $conn->close();
}

/**
 * Actualiza los datos del perfil del usuario logueado.
 * @param mysqli $conn Objeto de conexión.
 * @param int $user_id ID del usuario.
 */
function handleUpdateProfile($conn, $user_id) {
    $input = json_decode(file_get_contents('php://input'), true);

    $full_name = isset($input['full_name']) ? sanitizeInput($input['full_name']) : '';
    $phone_number = isset($input['phone_number']) ? sanitizeInput($input['phone_number']) : '';
    $location = isset($input['location']) ? sanitizeInput($input['location']) : '';
    $bio = isset($input['bio']) ? sanitizeInput($input['bio']) : '';

    // Obtener el tipo de usuario actual para saber qué campos actualizar
    $current_user_type_stmt = $conn->prepare("SELECT user_type FROM Users WHERE user_id = ?");
    $current_user_type_stmt->bind_param("i", $user_id);
    $current_user_type_stmt->execute();
    $current_user_type_result = $current_user_type_stmt->get_result();
    $current_user_type_row = $current_user_type_result->fetch_assoc();
    $current_user_type = $current_user_type_row['user_type'];
    $current_user_type_stmt->close();

    $work_experience = null;
    $skills = null;
    $company_name = null;
    $company_role = null;

    if ($current_user_type === 'applicant') {
        $work_experience = isset($input['work_experience']) ? sanitizeInput($input['work_experience']) : '';
        $skills = isset($input['skills']) ? sanitizeInput($input['skills']) : '';
        $query = "UPDATE Users SET full_name = ?, phone_number = ?, location = ?, bio = ?, work_experience = ?, skills = ? WHERE user_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssssssi", $full_name, $phone_number, $location, $bio, $work_experience, $skills, $user_id);
    } elseif ($current_user_type === 'employer' || $current_user_type === 'company') { // MODIFICADO
        $company_name = isset($input['company_name']) ? sanitizeInput($input['company_name']) : '';
        $company_role = isset($input['company_role']) ? sanitizeInput($input['company_role']) : '';
        $query = "UPDATE Users SET full_name = ?, phone_number = ?, location = ?, bio = ?, company_name = ?, company_role = ? WHERE user_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssssssi", $full_name, $phone_number, $location, $bio, $company_name, $company_role, $user_id);
    } else {
        sendJsonResponse(['success' => false, 'message' => 'Tipo de usuario desconocido.'], 400);
    }

    if (empty($full_name)) {
        sendJsonResponse(['success' => false, 'message' => 'El nombre completo es obligatorio.'], 400);
    }

    if ($stmt->execute()) {
        // Actualizar el nombre completo en la sesión si es necesario
        $_SESSION['full_name'] = $full_name;
        sendJsonResponse(['success' => true, 'message' => 'Perfil actualizado exitosamente.']);
    } else {
        sendJsonResponse(['success' => false, 'message' => 'Error al actualizar el perfil: ' . $stmt->error], 500);
    }
    $stmt->close();
    $conn->close();
}

?>