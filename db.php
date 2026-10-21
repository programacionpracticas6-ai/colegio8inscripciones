<?php
// Configuración de la conexión a la Base de Datos MySQL Local
define('DB_HOST', 'localhost');
define('DB_USER', 'root');      // Usuario por defecto de XAMPP/WAMP
define('DB_PASS', '');          // Contraseña por defecto (vacía)
define('DB_NAME', 'colegio_provincial_8');
define('DB_PORT', 3306);

function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    
    if ($conn->connect_error) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Error de conexión a la Base de Datos Local MySQL: " . $conn->connect_error
        ]);
        exit();
    }
    
    $conn->set_charset("utf8mb4");
    return $conn;
}
?>