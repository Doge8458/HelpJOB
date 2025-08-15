<?php
// api/applications.php

require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// --- INICIO: DEPURACIÓN ---
error_reporting(E_ALL);
ini_set('display_errors', 1);
// --- FIN: DEPURACIÓN ---

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$action = isset($_GET['action']) ? sanitizeInput($_GET['action']) : '';

if (!isLoggedIn() && !in_array($action, ['list_comments', 'get_avg_rating'])) {
    sendJsonResponse(['success' => false, 'message' => 'Acceso denegado. Se requiere autenticación.'], 401);
}

$user_id = getLoggedInUserId();
$user_type = getLoggedInUserType();
$method = $_SERVER['REQUEST_METHOD']; // --- CÓDIGO AÑADIDO ---

switch ($action) {
    case 'apply':
        if ($method === 'POST' && $user_type === 'applicant') {
            handleApplyJob($conn, $user_id);
        } else {
            sendJsonResponse(['success' => false, 'message' => 'Acción no permitida.'], 403);
        }
        break;
    case 'save_job':
        if ($method === 'POST' && $user_type === 'applicant') {
            handleSaveJob($conn, $user_id);
        } else {
            sendJsonResponse(['success' => false, 'message' => 'Solo los aspirantes pueden guardar empleos.'], 403);
        }
        break;
    case 'update_application_status':
        if ($method === 'POST' && ($user_type === 'employer' || $user_type === 'company')) {
            handleEmployerUpdateApplicationStatus($conn, $user_id);
        } else {
            sendJsonResponse(['success' => false, 'message' => 'Acción no permitida.'], 403);
        }
        break;
    case 'list_job_applications':
        // --- CÓDIGO MODIFICADO ---
        if ($method === 'GET' && ($user_type === 'employer' || $user_type === 'company')) {
            handleListJobApplications($conn, $user_id);
        } else {
            sendJsonResponse(['success' => false, 'message' => 'Acción no permitida.'], 403);
        }
        break;
    case 'list_my_applications':
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && $user_type === 'applicant') {
            handleListMyApplications($conn, $user_id);
        } else {
            sendJsonResponse(['success' => false, 'message' => 'Acción no permitida.'], 403);
        }
        break;
    case 'assign_job':
        // MODIFICADO: Acepta PUT y POST para compatibilidad JS
        if (in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'POST']) && ($user_type === 'employer' || $user_type === 'company')) {
            handleAssignJob($conn, $user_id);
        } else {
            sendJsonResponse(['success' => false, 'message' => 'Acción no permitida.'], 403);
        }
        break;
    case 'add_comment_rating':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_type === 'applicant') {
            handleAddCommentRating($conn, $user_id);
        } else {
            sendJsonResponse(['success' => false, 'message' => 'Solo los aspirantes pueden comentar.'], 403);
        }
        break;
    case 'list_comments':
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            handleListComments($conn);
        }
        break;
    case 'get_avg_rating':
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            handleGetAvgRating($conn);
        }
        break;
    case 'list_saved_jobs':
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && $user_type === 'applicant') {
            handleListSavedJobs($conn, $user_id);
        } else {
            sendJsonResponse(['success' => false, 'message' => 'Acción no permitida.'], 403);
        }
        break;
        
    default:
        sendJsonResponse(['success' => false, 'message' => 'Acción no reconocida.'], 400);
        break;
}

/**
 * Maneja la aplicación de un aspirante a un empleo.
 * @param mysqli $conn Objeto de conexión.
 * @param int $applicant_id ID del aspirante.
 */
function handleApplyJob($conn, $applicant_id) {
    $input = json_decode(file_get_contents('php://input'), true);
    $post_id = isset($input['post_id']) ? (int)$input['post_id'] : 0;
    $applicant_message = isset($input['message']) ? sanitizeInput($input['message']) : '';
    if ($post_id <= 0) {
        sendJsonResponse(['success' => false, 'message' => 'ID de publicación inválido.'], 400);
        return;
    }
    $stmt = $conn->prepare("SELECT application_id FROM Applications WHERE applicant_id = ? AND post_id = ?");
    $stmt->bind_param("ii", $applicant_id, $post_id);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close();
        sendJsonResponse(['success' => false, 'message' => 'Ya has aplicado a este empleo.'], 409);
        return;
    }
    $stmt->close();
    $query = "INSERT INTO Applications (applicant_id, post_id, applicant_message, application_status) VALUES (?, ?, ?, 'pending')";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iis", $applicant_id, $post_id, $applicant_message);
    if ($stmt->execute()) {
        $update_count_stmt = $conn->prepare("UPDATE JobPostings SET application_count = application_count + 1 WHERE post_id = ?");
        $update_count_stmt->bind_param("i", $post_id);
        $update_count_stmt->execute();
        $update_count_stmt->close();
        sendJsonResponse(['success' => true, 'message' => 'Aplicación enviada exitosamente.']);
    } else {
        sendJsonResponse(['success' => false, 'message' => 'Error al enviar la aplicación: ' . $stmt->error], 500);
    }
    $stmt->close();
}

/**
 * Maneja el guardado de una publicación por un aspirante.
 * @param mysqli $conn Objeto de conexión.
 * @param int $applicant_id ID del aspirante.
 */
function handleSaveJob($conn, $applicant_id) {
    $input = json_decode(file_get_contents('php://input'), true);
    $post_id = isset($input['post_id']) ? (int)$input['post_id'] : 0;
    if ($post_id <= 0) {
        sendJsonResponse(['success' => false, 'message' => 'ID de publicación inválido.'], 400);
        return;
    }
    $stmt = $conn->prepare("SELECT saved_id FROM SavedPosts WHERE applicant_id = ? AND post_id = ?");
    $stmt->bind_param("ii", $applicant_id, $post_id);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close();
        // --- INICIO: CÓDIGO AÑADIDO (Eliminar de guardados) ---
        $delete_stmt = $conn->prepare("DELETE FROM SavedPosts WHERE applicant_id = ? AND post_id = ?");
        $delete_stmt->bind_param("ii", $applicant_id, $post_id);
        if ($delete_stmt->execute()) {
            sendJsonResponse(['success' => true, 'message' => 'Publicación eliminada de tus guardados.', 'action' => 'unsaved']);
        } else {
            sendJsonResponse(['success' => false, 'message' => 'Error al eliminar la publicación.'], 500);
        }
        $delete_stmt->close();
        // --- FIN: CÓDIGO AÑADIDO ---
        return;
    }
    $stmt->close();
    $query = "INSERT INTO SavedPosts (applicant_id, post_id) VALUES (?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $applicant_id, $post_id);
    if ($stmt->execute()) {
        sendJsonResponse(['success' => true, 'message' => 'Publicación guardada exitosamente.', 'action' => 'saved']);
    } else {
        sendJsonResponse(['success' => false, 'message' => 'Error al guardar la publicación.'], 500);
    }
    $stmt->close();
}

/**
 * Actualiza el estado de una aplicación (aceptar/rechazar).
 * @param mysqli $conn Objeto de conexión.
 * @param int $employer_id ID del empleador/empresa logueado.
 */
function handleEmployerUpdateApplicationStatus($conn, $employer_id) {
    $input = json_decode(file_get_contents('php://input'), true);
    $application_id = isset($input['application_id']) ? (int)$input['application_id'] : 0;
    $new_status = isset($input['status']) ? sanitizeInput($input['status']) : '';

    // Solo permitir 'accepted' o 'rejected'
    if ($application_id <= 0 || !in_array($new_status, ['accepted', 'rejected'])) {
        sendJsonResponse(['success' => false, 'message' => 'Datos de estado inválidos.'], 400);
        return;
    }

    // Verificar que el empleador tiene permiso sobre esta aplicación
    $query_check = "SELECT a.post_id, a.applicant_id FROM Applications a JOIN JobPostings p ON a.post_id = p.post_id WHERE a.application_id = ? AND p.employer_id = ?";
    $stmt_check = $conn->prepare($query_check);
    $stmt_check->bind_param("ii", $application_id, $employer_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    if ($result_check->num_rows === 0) {
        sendJsonResponse(['success' => false, 'message' => 'No tienes permiso para actualizar esta aplicación.'], 403);
        return;
    }
    $application_data = $result_check->fetch_assoc();
    $stmt_check->close();

    $conn->begin_transaction();
    try {
        // Actualizar el estado de la aplicación
        $update_stmt = $conn->prepare("UPDATE Applications SET application_status = ? WHERE application_id = ?");
        $update_stmt->bind_param("si", $new_status, $application_id);
        $update_stmt->execute();
        $update_stmt->close();

        // Si se acepta, marcar el puesto como "asignado"
        if ($new_status === 'accepted') {
            $post_id_to_update = $application_data['post_id'];
            $applicant_id_to_assign = $application_data['applicant_id'];

            $assign_stmt = $conn->prepare(
                "UPDATE JobPostings SET post_status = 'assigned', assigned_applicant_id = ?, assignment_date = NOW(), expiration_date_visible = (NOW() + INTERVAL 2 DAY) WHERE post_id = ?"
            );
            $assign_stmt->bind_param("ii", $applicant_id_to_assign, $post_id_to_update);
            $assign_stmt->execute();
            $assign_stmt->close();
        }

        $conn->commit();
        sendJsonResponse(['success' => true, 'message' => 'Estado de la aplicación actualizado.']);
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error en handleEmployerUpdateApplicationStatus: " . $e->getMessage());
        sendJsonResponse(['success' => false, 'message' => 'Error al actualizar el estado.'], 500);
    }
}

/**
 * Asigna un empleo a un aspirante (empleador/empresa).
 * @param mysqli $conn Objeto de conexión.
 * @param int $employer_id ID del empleador/empresa logueado.
 */
function handleAssignJob($conn, $employer_id) {
    $input = json_decode(file_get_contents('php://input'), true);
    $post_id = isset($input['post_id']) ? (int)$input['post_id'] : 0;
    $applicant_id = isset($input['applicant_id']) ? (int)$input['applicant_id'] : 0;

    if ($post_id <= 0 || $applicant_id <= 0) {
        sendJsonResponse(['success' => false, 'message' => 'IDs de publicación o aspirante inválidos.'], 400);
        return;
    }

    // Verificar que el empleador es dueño de la publicación
    $check_stmt = $conn->prepare("SELECT post_id FROM JobPostings WHERE post_id = ? AND employer_id = ?");
    $check_stmt->bind_param("ii", $post_id, $employer_id);
    $check_stmt->execute();
    $check_stmt->store_result();
    if ($check_stmt->num_rows === 0) {
        sendJsonResponse(['success' => false, 'message' => 'No tienes permiso para modificar esta publicación.'], 403);
        return;
    }
    $check_stmt->close();
    
    $conn->begin_transaction();
    try {
        // Actualizar el estado de la publicación
        $assign_stmt = $conn->prepare(
            "UPDATE JobPostings SET post_status = 'assigned', assigned_applicant_id = ?, assignment_date = NOW(), expiration_date_visible = (NOW() + INTERVAL 2 DAY) WHERE post_id = ?"
        );
        $assign_stmt->bind_param("ii", $applicant_id, $post_id);
        $assign_stmt->execute();
        $assign_stmt->close();

        // Actualizar el estado de la aplicación a 'accepted'
        $app_stmt = $conn->prepare("UPDATE Applications SET application_status = 'accepted' WHERE post_id = ? AND applicant_id = ?");
        $app_stmt->bind_param("ii", $post_id, $applicant_id);
        $app_stmt->execute();
        $app_stmt->close();

        $conn->commit();
        sendJsonResponse(['success' => true, 'message' => 'Empleo asignado exitosamente.']);
    } catch (Exception $e) {
        $conn->rollback();
        sendJsonResponse(['success' => false, 'message' => 'Error al asignar el empleo: ' . $e->getMessage()], 500);
    }
}

/**
 * Lista los comentarios de una publicación.
 * @param mysqli $conn Objeto de conexión.
 */
function handleListComments($conn) {
    $post_id = isset($_GET['post_id']) ? (int)sanitizeInput($_GET['post_id']) : 0;

    if ($post_id <= 0) {
        sendJsonResponse(['success' => false, 'message' => 'ID de publicación inválido.'], 400);
    }

    $sql = "SELECT c.*, u.full_name, u.profile_image_path, r.score
            FROM Comments c
            JOIN Users u ON c.user_id = u.user_id
            LEFT JOIN Ratings r ON c.user_id = r.user_id AND c.post_id = r.post_id
            WHERE c.post_id = ?
            ORDER BY c.comment_date DESC";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        sendJsonResponse(['success' => false, 'message' => 'Error de SQL: ' . $conn->error], 500);
        return;
    }
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $comments = [];
    while ($row = $result->fetch_assoc()) {
        $comments[] = $row;
    }

    sendJsonResponse(['success' => true, 'comments' => $comments]);
    $stmt->close();
    $conn->close();
}

/**
 * Obtiene la calificación promedio de una publicación.
 * @param mysqli $conn Objeto de conexión.
 */
function handleGetAvgRating($conn) {
    $post_id = isset($_GET['post_id']) ? (int)sanitizeInput($_GET['post_id']) : 0;

    if ($post_id <= 0) {
        sendJsonResponse(['success' => false, 'message' => 'ID de publicación inválido.'], 400);
    }

    $sql = "SELECT AVG(score) as avg_score, COUNT(rating_id) as total_ratings
            FROM Ratings
            WHERE post_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    sendJsonResponse(['success' => true, 'avg_score' => $row['avg_score'], 'total_ratings' => $row['total_ratings']]);
    $stmt->close();
    $conn->close();
}

/**
 * Lista las publicaciones guardadas por un aspirante.
 * @param mysqli $conn Objeto de conexión.
 * @param int $applicant_id ID del aspirante.
 */
function handleListSavedJobs($conn, $applicant_id) {
    $sql = "SELECT sp.saved_id, sp.post_id, p.title, p.company_name_posting, p.job_location_text, p.job_type, p.salary, p.post_date, p.category, u.profile_image_path as employer_profile_image
            FROM SavedPosts sp
            JOIN JobPostings p ON sp.post_id = p.post_id
            JOIN Users u ON p.employer_id = u.user_id
            WHERE sp.applicant_id = ? AND p.post_status = 'active'
            ORDER BY sp.save_date DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $applicant_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $saved_jobs = $result->fetch_all(MYSQLI_ASSOC);

    sendJsonResponse(['success' => true, 'saved_jobs' => $saved_jobs]);
    $stmt->close();
}

function handleListJobApplications($conn, $employer_id) {
    $post_id = isset($_GET['post_id']) ? (int)$_GET['post_id'] : 0;
    if ($post_id <= 0) {
        sendJsonResponse(['success' => false, 'message' => 'ID de publicación inválido.'], 400);
        return;
    }
    $check_stmt = $conn->prepare("SELECT title FROM JobPostings WHERE post_id = ? AND employer_id = ?");
    if ($check_stmt === false) {
        sendJsonResponse(['success' => false, 'message' => 'Error de SQL al verificar permisos: ' . $conn->error], 500);
        return;
    }
    $check_stmt->bind_param("ii", $post_id, $employer_id);
    $check_stmt->execute();
    $check_stmt->bind_result($job_title);
    $check_stmt->fetch();
    $check_stmt->close();

    if (!$job_title) {
        sendJsonResponse(['success' => false, 'message' => 'No tienes permiso para ver estas aplicaciones o la publicación no existe.'], 403);
        return;
    }

    $sql = "SELECT a.*, u.full_name, u.email, u.phone_number, u.profile_image_path, u.bio, u.skills, u.work_experience, u.cv_pdf_path 
            FROM Applications a 
            JOIN Users u ON a.applicant_id = u.user_id 
            WHERE a.post_id = ? 
            ORDER BY a.application_date DESC";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        sendJsonResponse(['success' => false, 'message' => 'Error de SQL al obtener aplicantes: ' . $conn->error], 500);
        return;
    }
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $applications = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    sendJsonResponse(['success' => true, 'applications' => $applications, 'job_title' => $job_title]);
}

function handleListMyApplications($conn, $applicant_id) {
    $sql = "SELECT a.application_id, a.post_id, a.application_status, p.title, p.company_name_posting
            FROM Applications a
            JOIN JobPostings p ON a.post_id = p.post_id
            WHERE a.applicant_id = ?
            ORDER BY a.application_date DESC";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        sendJsonResponse(['success' => false, 'message' => 'Error de SQL al obtener mis aplicaciones: ' . $conn->error], 500);
        return;
    }
    $stmt->bind_param("i", $applicant_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $applications = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    sendJsonResponse(['success' => true, 'applications' => $applications]);
}
?>