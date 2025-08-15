<?php
// api/auth.php

// Incluir los archivos necesarios
require_once '../includes/db_connect.php'; // Conexión a la base de datos
require_once '../includes/functions.php'; // Funciones de utilidad

// Establecer la cabecera Content-Type para respuestas JSON
header('Content-Type: application/json');

// Permitir solicitudes de diferentes orígenes
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Manejar solicitudes OPTIONS (preflight para CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? sanitizeInput($_GET['action']) : '';

// Procesar la solicitud
switch ($action) {
    case 'register':
        if ($method === 'POST') {
            handleRegister($conn);
        } else {
            sendJsonResponse(['success' => false, 'message' => 'Método no permitido para registro.'], 405);
        }
        break;
    case 'login':
        if ($method === 'POST') {
            handleLogin($conn);
        } else {
            sendJsonResponse(['success' => false, 'message' => 'Método no permitido para inicio de sesión.'], 405);
        }
        break;
    case 'logout':
        if ($method === 'GET') {
            handleLogout();
        } else {
            sendJsonResponse(['success' => false, 'message' => 'Método no permitido para cierre de sesión.'], 405);
        }
        break;
    case 'check_session':
        if ($method === 'GET') {
            // SE PASA LA CONEXIÓN A LA FUNCIÓN
            handleCheckSession($conn);
        } else {
            sendJsonResponse(['success' => false, 'message' => 'Método no permitido para verificar sesión.'], 405);
        }
        break;
    default:
        sendJsonResponse(['success' => false, 'message' => 'Acción no reconocida.'], 400);
        break;
}

/**
 * Maneja la lógica de registro de nuevos usuarios.
 * (Esta función es TU versión original, no se ha cambiado nada)
 */
function handleRegister($conn) {
    $input = json_decode(file_get_contents('php://input'), true);

    $full_name = isset($input['full_name']) ? sanitizeInput($input['full_name']) : '';
    $email = isset($input['email']) ? sanitizeInput($input['email']) : '';
    $password = isset($input['password']) ? $input['password'] : '';
    $confirm_password = isset($input['confirm_password']) ? $input['confirm_password'] : '';
    $phone_number = isset($input['phone_number']) ? sanitizeInput($input['phone_number']) : '';
    $location = isset($input['location']) ? sanitizeInput($input['location']) : '';
    $user_type = isset($input['user_type']) ? sanitizeInput($input['user_type']) : '';
    $bio = isset($input['bio']) ? sanitizeInput($input['bio']) : '';
    $work_experience = '';
    $skills = '';
    $company_name = '';
    $company_role = '';

    if (empty($full_name) || empty($email) || empty($password) || empty($confirm_password) || empty($user_type)) {
        sendJsonResponse(['success' => false, 'message' => 'Por favor, complete todos los campos obligatorios.'], 400);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendJsonResponse(['success' => false, 'message' => 'Formato de email inválido.'], 400);
    }
    if ($password !== $confirm_password) {
        sendJsonResponse(['success' => false, 'message' => 'Las contraseñas no coinciden.'], 400);
    }
    if (strlen($password) < 6) {
        sendJsonResponse(['success' => false, 'message' => 'La contraseña debe tener al menos 6 caracteres.'], 400);
    }
    if (!in_array($user_type, ['applicant', 'employer', 'company'])) {
        sendJsonResponse(['success' => false, 'message' => 'Tipo de usuario inválido.'], 400);
    }

    $password_hash = hashPassword($password);

    if ($user_type === 'applicant') {
        $work_experience = isset($input['work_experience']) ? sanitizeInput($input['work_experience']) : '';
        $skills = isset($input['skills']) ? sanitizeInput($input['skills']) : '';
    } elseif ($user_type === 'employer' || $user_type === 'company') {
        $company_name = isset($input['company_name']) ? sanitizeInput($input['company_name']) : '';
        $company_role = isset($input['company_role']) ? sanitizeInput($input['company_role']) : '';
        if (empty($company_name) || empty($company_role)) {
             sendJsonResponse(['success' => false, 'message' => 'Por favor, complete los campos de empresa y cargo.'], 400);
        }
    }

    $stmt = $conn->prepare("SELECT user_id FROM Users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close();
        sendJsonResponse(['success' => false, 'message' => 'Este email ya está registrado.'], 409);
    }
    $stmt->close();

    $query = "INSERT INTO Users (full_name, email, password_hash, phone_number, location, user_type, bio, work_experience, skills, company_name, company_role) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sssssssssss", $full_name, $email, $password_hash, $phone_number, $location, $user_type, $bio, $work_experience, $skills, $company_name, $company_role);

    if ($stmt->execute()) {
        $user_id = $stmt->insert_id;
        createSession($user_id, $user_type, $full_name);
        sendJsonResponse(['success' => true, 'message' => 'Registro exitoso.', 'user_id' => $user_id, 'user_type' => $user_type, 'full_name' => $full_name]);
    } else {
        sendJsonResponse(['success' => false, 'message' => 'Error al registrar el usuario: ' . $stmt->error], 500);
    }
    $stmt->close();
    $conn->close();
}

/**
 * Maneja la lógica de inicio de sesión de usuarios.
 * (Esta función es TU versión original, no se ha cambiado nada)
 */
function handleLogin($conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    $email = isset($input['email']) ? sanitizeInput($input['email']) : '';
    $password = isset($input['password']) ? $input['password'] : '';

    if (empty($email) || empty($password)) {
        sendJsonResponse(['success' => false, 'message' => 'Por favor, ingrese email y contraseña.'], 400);
    }

    $stmt = $conn->prepare("SELECT user_id, full_name, password_hash, user_type FROM Users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (verifyPassword($password, $user['password_hash'])) {
            createSession($user['user_id'], $user['user_type'], $user['full_name']);
            sendJsonResponse(['success' => true, 'message' => 'Inicio de sesión exitoso.', 'user_id' => $user['user_id'], 'user_type' => $user['user_type'], 'full_name' => $user['full_name']]);
        } else {
            sendJsonResponse(['success' => false, 'message' => 'Credenciales inválidas.'], 401);
        }
    } else {
        sendJsonResponse(['success' => false, 'message' => 'Credenciales inválidas.'], 401);
    }
    $stmt->close();
    $conn->close();
}

/**
 * Maneja la lógica de cierre de sesión.
 * (Esta función es TU versión original, no se ha cambiado nada)
 */
function handleLogout() {
    destroySession();
    sendJsonResponse(['success' => true, 'message' => 'Sesión cerrada exitosamente.']);
}

/**
 * Maneja la lógica para verificar si hay una sesión activa.
 * ----- ÚNICA MODIFICACIÓN -----
 * @param mysqli $conn Se necesita la conexión para consultar la DB.
 */
function handleCheckSession($conn) { // <-- Se añade $conn como parámetro
    if (isLoggedIn()) {
        $user_id = getLoggedInUserId();
        
        // Consulta a la base de datos para obtener la información MÁS RECIENTE del usuario
        $stmt = $conn->prepare("SELECT user_id, full_name, user_type, profile_image_path FROM Users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_data = $result->fetch_assoc();
        $stmt->close();

        if ($user_data) {
            // Enviar los datos frescos desde la base de datos
            sendJsonResponse([
                'success' => true,
                'logged_in' => true,
                'user_id' => $user_data['user_id'],
                'user_type' => $user_data['user_type'],
                'full_name' => $user_data['full_name'],
                'profile_image_path' => $user_data['profile_image_path'] // <-- CAMBIO CLAVE: Se envía la ruta de la imagen actualizada
            ]);
        } else {
            destroySession();
            sendJsonResponse(['success' => false, 'logged_in' => false, 'message' => 'Usuario de sesión no encontrado.']);
        }
    } else {
        sendJsonResponse(['success' => false, 'logged_in' => false]);
    }
}
?>