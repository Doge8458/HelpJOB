<?php
// api/jobs.php

require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$action = isset($_GET['action']) ? sanitizeInput($_GET['action']) : '';
$method = $_SERVER['REQUEST_METHOD'];

// ===================================================================
// INICIO: LÓGICA DE PERMISOS CORREGIDA
// ===================================================================

// 1. Manejar acciones públicas que todos pueden ver
if ($action === 'list') {
    handleListJobs($conn);
    exit(); // Terminar el script aquí, la acción pública ya se completó
}
if ($action === 'get_single') {
    handleGetSingleJob($conn);
    exit(); // Terminar el script aquí
}

// 2. Para cualquier otra acción, el usuario DEBE estar logueado
if (!isLoggedIn()) {
    sendJsonResponse(['success' => false, 'message' => 'Acceso denegado. Se requiere autenticación.'], 401);
    exit();
}

// Obtenemos los datos del usuario que ya sabemos que está logueado
$user_id = getLoggedInUserId();
$user_type = getLoggedInUserType();

// 3. Manejar acciones que son EXCLUSIVAS para empleadores/empresas
$employer_actions = ['list_by_employer', 'create', 'update', 'delete'];
if (in_array($action, $employer_actions)) {
    // Verificar que el tipo de usuario sea el correcto
    if ($user_type !== 'employer' && $user_type !== 'company') {
        sendJsonResponse(['success' => false, 'message' => 'No tienes permiso para realizar esta acción.'], 403);
        exit();
    }

    // Si el permiso es correcto, procedemos con la acción solicitada
    switch ($action) {
        case 'list_by_employer':
            handleListJobsByEmployer($conn, $user_id);
            break;
        case 'create':
            if ($method === 'POST') handleCreateJob($conn, $user_id);
            break;
        case 'update':
            if ($method === 'POST') handleUpdateJob($conn, $user_id);
            break;
        case 'delete':
            if ($method === 'POST') handleDeleteJob($conn, $user_id);
            break;
    }
} else {
    // Si la acción no es pública ni de empleador, no es reconocida
    sendJsonResponse(['success' => false, 'message' => 'Acción no reconocida.'], 400);
}

// ===================================================================
// FIN: LÓGICA DE PERMISOS CORREGIDA
// ===================================================================


// --- DE AQUÍ EN ADELANTE VAN LAS FUNCIONES (handleListJobs, etc.) SIN CAMBIOS ---
// Asegúrate de que todas las funciones que definimos en los pasos anteriores sigan aquí debajo.

function handleListJobs($conn) {
    // Definir cuántos trabajos mostrar por página
    $items_per_page = 6;

    // Obtener los parámetros de la URL
    $search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
    $category = isset($_GET['category']) ? sanitizeInput($_GET['category']) : '';
    $job_type = isset($_GET['job_type']) ? sanitizeInput($_GET['job_type']) : '';
    $sort_by = isset($_GET['sort_by_date']) && $_GET['sort_by_date'] === 'oldest' ? 'ASC' : 'DESC';
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($page < 1) {
        $page = 1;
    }

    // --- Lógica para la paginación ---
    $base_sql = "FROM JobPostings p JOIN Users u ON p.employer_id = u.user_id WHERE p.post_status = 'active'";
    $where_clauses = [];
    $params = [];
    $types = '';

    if (!empty($search)) {
        $where_clauses[] = "(p.title LIKE ? OR p.description LIKE ? OR p.company_name_posting LIKE ?)";
        $searchTerm = "%" . $search . "%";
        array_push($params, $searchTerm, $searchTerm, $searchTerm);
        $types .= 'sss';
    }
    if (!empty($category)) {
        $where_clauses[] = "p.category = ?";
        $params[] = $category;
        $types .= 's';
    }
    if (!empty($job_type)) {
        $where_clauses[] = "p.job_type = ?";
        $params[] = $job_type;
        $types .= 's';
    }

    if (!empty($where_clauses)) {
        $base_sql .= " AND " . implode(" AND ", $where_clauses);
    }

    // 1. Obtener el número TOTAL de trabajos que coinciden con los filtros
    $total_jobs_sql = "SELECT COUNT(p.post_id) as total " . $base_sql;
    $stmt_total = $conn->prepare($total_jobs_sql);
    if (!empty($params)) {
        $stmt_total->bind_param($types, ...$params);
    }
    $stmt_total->execute();
    $total_result = $stmt_total->get_result()->fetch_assoc();
    $total_jobs = $total_result['total'] ?? 0;
    $total_pages = ceil($total_jobs / $items_per_page);
    $stmt_total->close();

    // 2. Obtener los trabajos SOLO para la página actual
    $offset = ($page - 1) * $items_per_page;
    $jobs_sql = "SELECT p.*, u.profile_image_path as employer_profile_image, u.company_name as company_name_posting " . $base_sql . " ORDER BY p.post_date " . $sort_by . " LIMIT ? OFFSET ?";
    
    $params_with_pagination = $params;
    $params_with_pagination[] = $items_per_page;
    $params_with_pagination[] = $offset;
    $types_with_pagination = $types . 'ii';

    $stmt_jobs = $conn->prepare($jobs_sql);
    if (!empty($params_with_pagination)) {
        $stmt_jobs->bind_param($types_with_pagination, ...$params_with_pagination);
    }
    $stmt_jobs->execute();
    $jobs = $stmt_jobs->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_jobs->close();
    
    sendJsonResponse([
        'success' => true, 
        'jobs' => $jobs,
        'pagination' => [
            'total_jobs' => $total_jobs,
            'total_pages' => $total_pages,
            'current_page' => $page,
            'items_per_page' => $items_per_page
        ]
    ]);
}

function handleListJobsByEmployer($conn, $employer_id) {
    $sql = "SELECT * FROM JobPostings WHERE employer_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $employer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $jobs = $result->fetch_all(MYSQLI_ASSOC);
    sendJsonResponse(['success' => true, 'jobs' => $jobs]);
    $stmt->close();
}

function handleGetSingleJob($conn) {
    $post_id = isset($_GET['post_id']) ? (int)$_GET['post_id'] : 0;
    if ($post_id <= 0) {
        sendJsonResponse(['success' => false, 'message' => 'ID de publicación inválido.'], 400);
        return;
    }
    $sql = "SELECT p.*, u.full_name as employer_full_name, u.profile_image_path as employer_profile_image, u.company_name as employer_company_name 
            FROM JobPostings p 
            JOIN Users u ON p.employer_id = u.user_id 
            WHERE p.post_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $job = $result->fetch_assoc();
    if ($job) {
        sendJsonResponse(['success' => true, 'job' => $job]);
    } else {
        sendJsonResponse(['success' => false, 'message' => 'Publicación no encontrada.'], 404);
    }
    $stmt->close();
}

function handleCreateJob($conn, $employer_id) {
    $input = json_decode(file_get_contents('php://input'), true);
    $stmt_user = $conn->prepare("SELECT full_name, company_name FROM Users WHERE user_id = ?");
    $stmt_user->bind_param("i", $employer_id);
    $stmt_user->execute();
    $user_data = $stmt_user->get_result()->fetch_assoc();
    $stmt_user->close();
    if (!$user_data) {
        sendJsonResponse(['success' => false, 'message' => 'No se encontraron datos del empleador.'], 404);
        return;
    }
    $title = $input['title'] ?? '';
    $description = $input['description'] ?? '';
    $job_location_text = $input['job_location_text'] ?? '';
    $job_type = $input['job_type'] ?? '';
    $category = $input['category'] ?? '';
    $requirements = $input['requirements'] ?? '';
    $benefits = $input['benefits'] ?? '';
    $latitude = !empty($input['latitude']) ? (float)$input['latitude'] : null;
    $longitude = !empty($input['longitude']) ? (float)$input['longitude'] : null;
    $salary = $input['salary'] ?? '';
    $company_name_posting = $user_data['company_name'];
    $employer_name_posting = $user_data['full_name'];
    $post_status = 'active';
    $query = "INSERT INTO JobPostings (employer_id, title, company_name_posting, employer_name_posting, description, requirements, benefits, job_location_text, latitude, longitude, job_type, salary, category, post_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("issssssddsssss", $employer_id, $title, $company_name_posting, $employer_name_posting, $description, $requirements, $benefits, $job_location_text, $latitude, $longitude, $job_type, $salary, $category, $post_status);
    if ($stmt->execute()) {
        sendJsonResponse(['success' => true, 'message' => 'Publicación de empleo creada exitosamente.', 'post_id' => $stmt->insert_id]);
    } else {
        sendJsonResponse(['success' => false, 'message' => 'Error al crear la publicación: ' . $stmt->error], 500);
    }
    $stmt->close();
}

function handleUpdateJob($conn, $employer_id) {
    $input = json_decode(file_get_contents('php://input'), true);
    $post_id = $input['post_id'] ?? 0;
    if ($post_id <= 0) {
        sendJsonResponse(['success' => false, 'message' => 'ID de publicación no proporcionado.'], 400);
        return;
    }
    $stmt_check = $conn->prepare("SELECT employer_id FROM JobPostings WHERE post_id = ?");
    $stmt_check->bind_param("i", $post_id);
    $stmt_check->execute();
    $stmt_check->bind_result($owner_id);
    $stmt_check->fetch();
    $stmt_check->close();
    if ($owner_id != $employer_id) {
        sendJsonResponse(['success' => false, 'message' => 'No tienes permiso para actualizar esta publicación.'], 403);
        return;
    }
    $title = $input['title'] ?? '';
    $description = $input['description'] ?? '';
    $requirements = $input['requirements'] ?? '';
    $benefits = $input['benefits'] ?? '';
    $job_location_text = $input['job_location_text'] ?? '';
    $latitude = !empty($input['latitude']) ? (float)$input['latitude'] : null;
    $longitude = !empty($input['longitude']) ? (float)$input['longitude'] : null;
    $job_type = $input['job_type'] ?? '';
    $salary = $input['salary'] ?? '';
    $category = $input['category'] ?? '';
    $query = "UPDATE JobPostings SET title = ?, description = ?, requirements = ?, benefits = ?, job_location_text = ?, latitude = ?, longitude = ?, job_type = ?, salary = ?, category = ? WHERE post_id = ? AND employer_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sssssddsssii", $title, $description, $requirements, $benefits, $job_location_text, $latitude, $longitude, $job_type, $salary, $category, $post_id, $employer_id);
    if ($stmt->execute()) {
        sendJsonResponse(['success' => true, 'message' => 'Publicación actualizada exitosamente.']);
    } else {
        sendJsonResponse(['success' => false, 'message' => 'Error al actualizar la publicación: ' . $stmt->error], 500);
    }
    $stmt->close();
}

function handleDeleteJob($conn, $employer_id) {
    $input = json_decode(file_get_contents('php://input'), true);
    $post_id = $input['post_id'] ?? 0;
    if ($post_id <= 0) {
        sendJsonResponse(['success' => false, 'message' => 'ID de publicación no proporcionado.'], 400);
        return;
    }
    $query = "DELETE FROM JobPostings WHERE post_id = ? AND employer_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $post_id, $employer_id);
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            sendJsonResponse(['success' => true, 'message' => 'Publicación eliminada exitosamente.']);
        } else {
            sendJsonResponse(['success' => false, 'message' => 'No se encontró la publicación o no tienes permiso para eliminarla.'], 404);
        }
    } else {
        sendJsonResponse(['success' => false, 'message' => 'Error al eliminar la publicación: ' . $stmt->error], 500);
    }
    $stmt->close();
}
?>
