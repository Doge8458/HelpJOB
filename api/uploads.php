<?php
// api/upload.php

require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *"); // En producción, restringe a tu dominio
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Manejar solicitudes OPTIONS (preflight para CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Asegurar que el usuario esté logueado
if (!isLoggedIn()) {
    sendJsonResponse(['success' => false, 'message' => 'Acceso denegado. Se requiere autenticación.'], 401);
}

$user_id = getLoggedInUserId();
$user_type = getLoggedInUserType();
$action = isset($_GET['action']) ? sanitizeInput($_GET['action']) : '';

switch ($action) {
    case 'profile_image':
        handleProfileImageUpload($conn, $user_id);
        break;
    case 'cv':
        handleCvUpload($conn, $user_id, $user_type);
        break;
    default:
        sendJsonResponse(['success' => false, 'message' => 'Acción de subida no reconocida.'], 400);
        break;
}

/**
 * Maneja la subida de la imagen de perfil del usuario.
 * @param mysqli $conn Objeto de conexión.
 * @param int $user_id ID del usuario.
 */
function handleProfileImageUpload($conn, $user_id) {
    if (!isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
        sendJsonResponse(['success' => false, 'message' => 'Error en la subida del archivo.'], 400);
    }

    $file = $_FILES['profile_image'];
    $allowed_types = ['image/jpeg', 'image/png'];
    $max_size = 5 * 1024 * 1024; // 5 MB

    if (!in_array($file['type'], $allowed_types)) {
        sendJsonResponse(['success' => false, 'message' => 'Tipo de archivo no permitido. Solo JPG y PNG.'], 400);
    }
    if ($file['size'] > $max_size) {
        sendJsonResponse(['success' => false, 'message' => 'El archivo es demasiado grande. Máximo 5MB.'], 400);
    }

    $upload_dir = '../uploads/profile_images/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true); // Crear directorio si no existe
    }

    // Generar un nombre de archivo único para evitar colisiones
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $new_file_name = uniqid('profile_') . '.' . $file_extension;
    $destination = $upload_dir . $new_file_name;

    if (move_uploaded_file($file['tmp_name'], $destination)) {
        // Actualizar la ruta en la base de datos
        $query = "UPDATE Users SET profile_image_path = ? WHERE user_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("si", $new_file_name, $user_id);

        if ($stmt->execute()) {
            sendJsonResponse(['success' => true, 'message' => 'Imagen de perfil subida y actualizada.', 'file_path' => $new_file_name]);
        } else {
            // Si la DB falla, eliminar el archivo subido para limpiar
            unlink($destination);
            sendJsonResponse(['success' => false, 'message' => 'Error al guardar la ruta de la imagen en la base de datos: ' . $stmt->error], 500);
        }
        $stmt->close();
    } else {
        sendJsonResponse(['success' => false, 'message' => 'Error al mover el archivo subido.'], 500);
    }
    $conn->close();
}

/**
 * Maneja la subida del CV en formato PDF para aspirantes.
 * @param mysqli $conn Objeto de conexión.
 * @param int $user_id ID del usuario.
 * @param string $user_type Tipo de usuario.
 */
function handleCvUpload($conn, $user_id, $user_type) {
    if ($user_type !== 'applicant') {
        sendJsonResponse(['success' => false, 'message' => 'Solo los aspirantes pueden subir CVs.'], 403);
    }

    if (!isset($_FILES['cv_pdf']) || $_FILES['cv_pdf']['error'] !== UPLOAD_ERR_OK) {
        sendJsonResponse(['success' => false, 'message' => 'Error en la subida del archivo CV.'], 400);
    }

    $file = $_FILES['cv_pdf'];
    $allowed_type = 'application/pdf';
    $max_size = 10 * 1024 * 1024; // 10 MB

    if ($file['type'] !== $allowed_type) {
        sendJsonResponse(['success' => false, 'message' => 'Tipo de archivo no permitido. Solo PDF.'], 400);
    }
    if ($file['size'] > $max_size) {
        sendJsonResponse(['success' => false, 'message' => 'El archivo CV es demasiado grande. Máximo 10MB.'], 400);
    }

    $upload_dir = '../uploads/cvs/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true); // Crear directorio si no existe
    }

    // Generar un nombre de archivo único
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $new_file_name = uniqid('cv_') . '.' . $file_extension;
    $destination = $upload_dir . $new_file_name;

    if (move_uploaded_file($file['tmp_name'], $destination)) {
        // Actualizar la ruta en la base de datos
        $query = "UPDATE Users SET cv_pdf_path = ? WHERE user_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("si", $new_file_name, $user_id);

        if ($stmt->execute()) {
            sendJsonResponse(['success' => true, 'message' => 'CV subido y actualizado.', 'file_path' => $new_file_name]);
        } else {
            unlink($destination);
            sendJsonResponse(['success' => false, 'message' => 'Error al guardar la ruta del CV en la base de datos: ' . $stmt->error], 500);
        }
        $stmt->close();
    } else {
        sendJsonResponse(['success' => false, 'message' => 'Error al mover el archivo CV subido.'], 500);
    }
    $conn->close();
}

?>