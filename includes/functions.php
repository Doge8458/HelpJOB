<?php
// includes/functions.php

// Inicia la sesión PHP si aún no está iniciada.
// Es crucial para manejar el estado de autenticación del usuario.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Hashea una contraseña usando el algoritmo PASSWORD_BCRYPT.
 * Esto es fundamental para la seguridad, nunca almacenar contraseñas en texto plano.
 * @param string $password La contraseña en texto plano.
 * @return string El hash de la contraseña.
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

/**
 * Verifica una contraseña en texto plano contra un hash.
 * @param string $password La contraseña en texto plano a verificar.
 * @param string $hash El hash almacenado en la base de datos.
 * @return bool True si la contraseña coincide, false en caso contrario.
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Crea una sesión de usuario tras un inicio de sesión exitoso.
 * Almacena el ID de usuario, el tipo de usuario y el nombre completo en variables de sesión.
 * @param int $userId El ID del usuario.
 * @param string $userType El tipo de usuario ('applicant' o 'employer').
 * @param string $fullName El nombre completo del usuario.
 */
function createSession($userId, $userType, $fullName) {
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_type'] = $userType;
    $_SESSION['full_name'] = $fullName;
    // Puedes añadir más datos relevantes aquí si es necesario
    $_SESSION['logged_in'] = true;
}

/**
 * Destruye la sesión actual del usuario.
 * Usado para el cierre de sesión.
 */
function destroySession() {
    $_SESSION = array(); // Vacía todas las variables de sesión
    if (ini_get("session.use_cookies")) {
        // Elimina la cookie de sesión
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy(); // Destruye la sesión
}

/**
 * Verifica si un usuario está actualmente logueado.
 * @return bool True si hay una sesión activa, false en caso contrario.
 */
function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Redirecciona al usuario a una URL especificada.
 * @param string $url La URL a la que redireccionar.
 */
function redirectTo($url) {
    header("Location: " . $url);
    exit(); // Es importante terminar el script después de una redirección
}

/**
 * Sanea y escapa una cadena para prevenir ataques de inyección SQL y XSS.
 * Debe usarse con PDO o MySQLi prepared statements para máxima seguridad contra SQL injection.
 * Para HTML, se recomienda usar htmlspecialchars() al mostrar datos.
 * @param string $data La cadena de entrada a sanear.
 * @return string La cadena saneada.
 */
function sanitizeInput($data) {
    $data = trim($data); // Elimina espacios en blanco del principio y final
    $data = stripslashes($data); // Elimina barras invertidas
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8'); // Convierte caracteres especiales a entidades HTML
    return $data;
}

/**
 * Función para enviar una respuesta JSON al cliente.
 * @param array $data Los datos a enviar en formato JSON.
 * @param int $statusCode El código de estado HTTP (por defecto 200 OK).
 */
function sendJsonResponse($data, $statusCode = 200) {
    header('Content-Type: application/json');
    http_response_code($statusCode);
    echo json_encode($data);
    exit();
}

/**
 * Obtiene el ID del usuario logueado.
 * @return int|null El ID del usuario o null si no hay sesión.
 */
function getLoggedInUserId() {
    return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
}

/**
 * Obtiene el tipo del usuario logueado.
 * @return string|null El tipo de usuario ('applicant' o 'employer') o null si no hay sesión.
 */
function getLoggedInUserType() {
    return isset($_SESSION['user_type']) ? $_SESSION['user_type'] : null;
}

/**
 * Obtiene el nombre completo del usuario logueado.
 * @return string|null El nombre completo o null si no hay sesión.
 */
function getLoggedInFullName() {
    return isset($_SESSION['full_name']) ? $_SESSION['full_name'] : null;
}

?>
