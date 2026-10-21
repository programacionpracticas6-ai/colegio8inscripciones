<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

require_once "../config/db.php";

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['id_solicitud']) || !isset($data['nuevo_estado'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Parámetros incompletos."]);
    exit();
}

$conn = getDBConnection();

$id_solicitud = (int)$data['id_solicitud'];
$nuevo_estado = $data['nuevo_estado'];

$estados_validos = ['PENDIENTE', 'REVISION_DOCUMENTAL', 'CONFIRMADA', 'RECHAZADA', 'CANCELADA'];

if (!in_array($nuevo_estado, $estados_validos)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Estado no válido."]);
    exit();
}

$stmt = $conn->prepare("UPDATE solicitudes_inscripcion SET estado_solicitud = ? WHERE id_solicitud = ?");
$stmt->bind_param("si", $nuevo_estado, $id_solicitud);

if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Estado actualizado a " . $nuevo_estado]);
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Error al actualizar en la base de datos."]);
}

$stmt->close();
$conn->close();
?>