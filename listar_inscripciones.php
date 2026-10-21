<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

require_once "../config/db.php";

$conn = getDBConnection();

$query = "SELECT 
            s.id_solicitud,
            s.numero_expediente,
            s.fecha_solicitud,
            s.estado_solicitud,
            s.tipo_inscripcion,
            e.dni AS estudiante_dni,
            CONCAT(e.apellido, ' ', e.nombre) AS estudiante_nombre_completo,
            e.fecha_nacimiento,
            e.genero,
            t.dni AS tutor_dni,
            CONCAT(t.apellido, ' ', t.nombre) AS tutor_nombre_completo,
            t.telefono_principal AS tutor_telefono,
            t.email AS tutor_email,
            o.anio_estudio,
            o.orientacion,
            o.turno,
            r.fotocopia_dni_alumno,
            r.fotocopia_dni_tutor,
            r.partida_nacimiento,
            r.certificado_septimo_o_pase
          FROM solicitudes_inscripcion s
          JOIN estudiantes e ON s.id_estudiante = e.id_estudiante
          JOIN tutores t ON e.id_tutor = t.id_tutor
          JOIN ofertas_academicas o ON s.id_oferta = o.id_oferta
          LEFT JOIN requisitos_documentales r ON s.id_solicitud = r.id_solicitud
          ORDER BY s.id_solicitud DESC";

$result = $conn->query($query);

$inscripciones = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $inscripciones[] = $row;
    }
}

echo json_encode([
    "status" => "success",
    "total" => count($inscripciones),
    "data" => $inscripciones
]);

$conn->close();
?>