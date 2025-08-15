<?php
// db_connect.php

// Constantes para la conexión a la base de datos
// Si estás usando XAMPP, lo más probable es que estos sean los valores por defecto
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root'); // Usuario por defecto de MySQL en XAMPP
define('DB_PASSWORD', '');     // Contraseña por defecto (vacía) de MySQL en XAMPP
define('DB_NAME', 'HelpJOB'); // Nombre de la base de datos actualizada a HelpJOB

// Intentar establecer la conexión a MySQL
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Verificar la conexión
if ($conn->connect_error) {
    // Si hay un error, mostrarlo y terminar el script
    die("ERROR: No se pudo conectar a la base de datos. " . $conn->connect_error);
}

// Opcional: Establecer el conjunto de caracteres a UTF-8 para evitar problemas con tildes y ñ
$conn->set_charset("utf8mb4");

// Puedes imprimir un mensaje para depuración si la conexión es exitosa
// echo "Conexión a la base de datos exitosa para HelpJOB.";

// Ahora, $conn es la variable que usarás para todas tus consultas SQL.
?>
