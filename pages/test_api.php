<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

$action = $_GET['action'] ?? 'list'; // Por defecto prueba la acción 'list'
$response = [];

switch ($action) {
    case 'list':
        $sql = "SELECT p.*, u.company_name as company_name_posting FROM JobPostings p JOIN Users u ON p.employer_id = u.user_id WHERE p.post_status = 'active'";
        $stmt = $conn->prepare($sql);
        break;
    
    case 'get_single':
        $post_id = $_GET['post_id'] ?? 0;
        if ($post_id <= 0) {
            die(json_encode(['success' => false, 'message' => 'Falta el post_id para la acción get_single']));
        }
        $sql = "SELECT * FROM JobPostings WHERE post_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $post_id);
        break;
        
    default:
        die(json_encode(['success' => false, 'message' => 'Acción no reconocida']));
}

if ($stmt === false) {
    die(json_encode(['success' => false, 'message' => 'Error de SQL: ' . $conn->error]));
}

$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_all(MYSQLI_ASSOC);

$response = ['success' => true, 'action' => $action, 'count' => count($data), 'data' => $data];

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

$stmt->close();
$conn->close();
?>
