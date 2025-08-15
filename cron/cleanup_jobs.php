<?php
// cron/cleanup_jobs.php
// Este script debe ser ejecutado por un cron job (en Linux) o el programador de tareas (en Windows).

// Incluir la conexión a la base de datos
require_once __DIR__ . '/../includes/db_connect.php';
// No necesitamos functions.php a menos que vayas a usar sendJsonResponse o isLoggedIn,
// ya que este es un script de fondo y no una API web.

// Verificar que la conexión a la base de datos sea exitosa
if ($conn->connect_error) {
    // Para scripts de cron, es mejor loguear el error en un archivo en lugar de morir silenciosamente
    error_log("CRON ERROR: No se pudo conectar a la base de datos para la limpieza de empleos: " . $conn->connect_error);
    exit(); // Terminar la ejecución del script si no hay conexión
}

echo "Iniciando proceso de limpieza de empleos...\n";

try {
    // 1. Marcar publicaciones 'assigned' que ya superaron su fecha de visibilidad (2 días después de asignación)
    // Cambiar de 'assigned' a 'expired_hidden' si expiration_date_visible es anterior a la fecha y hora actuales.
    $stmt_update_hidden = $conn->prepare(
        "UPDATE JobPostings
         SET post_status = 'expired_hidden'
         WHERE post_status = 'assigned'
         AND expiration_date_visible <= NOW()"
    );
    if ($stmt_update_hidden === false) {
        throw new Exception("Error al preparar la consulta para ocultar empleos asignados: " . $conn->error);
    }
    $stmt_update_hidden->execute();
    $rows_updated_hidden = $stmt_update_hidden->affected_rows;
    $stmt_update_hidden->close();

    echo "Empleos asignados ocultados (status: expired_hidden): " . $rows_updated_hidden . ".\n";

    // 2. Opcional: Marcar publicaciones 'active' que estén muy antiguas y no tengan aplicaciones o que el empleador haya abandonado
    // Por ejemplo, empleos activos que no han recibido aplicaciones en 60 días
    /*
    $stmt_update_stale = $conn->prepare(
        "UPDATE JobPostings
         SET post_status = 'expired_hidden'
         WHERE post_status = 'active'
         AND post_date < (NOW() - INTERVAL 60 DAY)
         AND application_count = 0" // Opcional: solo si no hay aplicaciones
    );
    if ($stmt_update_stale === false) {
        throw new Exception("Error al preparar la consulta para ocultar empleos antiguos: " . $conn->error);
    }
    $stmt_update_stale->execute();
    $rows_updated_stale = $stmt_update_stale->affected_rows;
    $stmt_update_stale->close();

    echo "Empleos antiguos ocultados (status: expired_hidden): " . $rows_updated_stale . ".\n";
    */

} catch (Exception $e) {
    error_log("CRON ERROR: Error en el script de limpieza de empleos: " . $e->getMessage());
    echo "ERROR: " . $e->getMessage() . "\n";
} finally {
    // Cerrar la conexión a la base de datos al finalizar
    $conn->close();
    echo "Proceso de limpieza de empleos finalizado.\n";
}

?>