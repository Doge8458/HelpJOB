<?php
// Mostrar errores para depuración
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

if (!isLoggedIn()) {
    sendJsonResponse(['success' => false, 'message' => 'Acceso denegado. Se requiere autenticación.'], 401);
}

$user_id = getLoggedInUserId();
$action = isset($_GET['action']) ? sanitizeInput($_GET['action']) : '';
$method = $_SERVER['REQUEST_METHOD'];

switch ($action) {
    case 'list_conversations':
        if ($method === 'GET') {
            handleListConversations($conn, $user_id);
        }
        break;
    case 'get_messages':
        if ($method === 'GET') {
            handleGetMessages($conn, $user_id);
        }
        break;
    case 'send_message':
        if ($method === 'POST') {
            handleSendMessage($conn, $user_id);
        }
        break;
    default:
        sendJsonResponse(['success' => false, 'message' => 'Acción de chat no reconocida.'], 400);
        break;
}

/**
 * Lista todas las conversaciones activas para el usuario actual.
 */
function handleListConversations($conn, $user_id) {
    $sql = "SELECT
                other_user.user_id,
                other_user.full_name,
                other_user.profile_image_path,
                latest_message.message_text,
                latest_message.send_date,
                (SELECT COUNT(*) FROM ChatMessages WHERE sender_id = other_user.user_id AND receiver_id = ? AND is_read = 0) as unread_count
            FROM (
                SELECT
                    CASE
                        WHEN sender_id = ? THEN receiver_id
                        ELSE sender_id
                    END as other_user_id,
                    MAX(message_id) as max_message_id
                FROM ChatMessages
                WHERE sender_id = ? OR receiver_id = ?
                GROUP BY
                    CASE
                        WHEN sender_id = ? THEN receiver_id
                        ELSE sender_id
                    END
            ) as conversations
            JOIN Users other_user ON conversations.other_user_id = other_user.user_id
            JOIN ChatMessages latest_message ON conversations.max_message_id = latest_message.message_id
            ORDER BY latest_message.send_date DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiiii", $user_id, $user_id, $user_id, $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $conversations = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    sendJsonResponse(['success' => true, 'conversations' => $conversations]);
}

/**
 * Obtiene los mensajes entre el usuario actual y otro usuario.
 */
function handleGetMessages($conn, $user_id) {
    $other_user_id = isset($_GET['other_user_id']) ? (int)$_GET['other_user_id'] : 0;
    if ($other_user_id <= 0) {
        sendJsonResponse(['success' => false, 'message' => 'ID de usuario inválido.'], 400);
        return;
    }

    // Marcar mensajes como leídos
    $update_sql = "UPDATE ChatMessages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("ii", $other_user_id, $user_id);
    $update_stmt->execute();
    $update_stmt->close();

    // Obtener mensajes
    $sql = "SELECT * FROM ChatMessages
            WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
            ORDER BY send_date ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiii", $user_id, $other_user_id, $other_user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $messages = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    sendJsonResponse(['success' => true, 'messages' => $messages]);
}

/**
 * Envía un mensaje de un usuario a otro.
 */
function handleSendMessage($conn, $user_id) {
    $input = json_decode(file_get_contents('php://input'), true);
    $receiver_id = isset($input['receiver_id']) ? (int)$input['receiver_id'] : 0;
    $message_text = isset($input['message_text']) ? trim($input['message_text']) : '';

    if ($receiver_id <= 0 || empty($message_text)) {
        sendJsonResponse(['success' => false, 'message' => 'Faltan datos para enviar el mensaje.'], 400);
        return;
    }

    // No permitir que un usuario se envíe mensajes a sí mismo
    if ($user_id === $receiver_id) {
        sendJsonResponse(['success' => false, 'message' => 'No puedes enviarte mensajes a ti mismo.'], 400);
        return;
    }

    $sanitized_message = sanitizeInput($message_text);

    // INSERT explícito con send_date
    $sql = "INSERT INTO ChatMessages (sender_id, receiver_id, message_text, send_date) VALUES (?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iis", $user_id, $receiver_id, $sanitized_message);

    if ($stmt->execute()) {
        $insert_id = $stmt->insert_id;
        $stmt->close();

        // Obtener el mensaje recién insertado para devolverlo al cliente
        $select_stmt = $conn->prepare("SELECT * FROM ChatMessages WHERE message_id = ?");
        $select_stmt->bind_param("i", $insert_id);
        $select_stmt->execute();
        $new_message = $select_stmt->get_result()->fetch_assoc();
        $select_stmt->close();

        sendJsonResponse(['success' => true, 'message' => 'Mensaje enviado.', 'sent_message' => $new_message]);
    } else {
        sendJsonResponse(['success' => false, 'message' => 'Error al enviar el mensaje: ' . $stmt->error], 500);
    }
}
?>
<script>
  const currentUserId = <?= $_SESSION['user_id']; ?>;
</script>

