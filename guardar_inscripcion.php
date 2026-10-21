<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once "../config/db.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Método no permitido. Utilice POST."]);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Datos de solicitud inválidos o vacíos."]);
    exit();
}

$conn = getDBConnection();
$conn->begin_transaction();

try {
    // 1. Insertar o actualizar Tutor
    $stmt_tutor = $conn->prepare("INSERT INTO tutores (dni, apellido, nombre, parentesco, telefono_principal, email, domicilio_calle, domicilio_numero, domicilio_barrio, ocupacion) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) 
        ON DUPLICATE KEY UPDATE id_tutor=LAST_INSERT_ID(id_tutor), telefono_principal=?, email=?, domicilio_calle=?, domicilio_numero=?, domicilio_barrio=?");
    
    $t_dni = $data['tutor_dni'] ?? '';
    $t_apellido = $data['tutor_apellido'] ?? '';
    $t_nombre = $data['tutor_nombre'] ?? '';
    $t_parentesco = $data['tutor_parentesco'] ?? 'TUTOR_LEGAL';
    $t_tel = $data['tutor_telefono'] ?? '';
    $t_email = $data['tutor_email'] ?? '';
    $t_calle = $data['tutor_calle'] ?? '';
    $t_num = $data['tutor_numero'] ?? '';
    $t_barrio = $data['tutor_barrio'] ?? '';
    $t_ocupacion = $data['tutor_ocupacion'] ?? '';

    $stmt_tutor->bind_param("sssssssssssssss", 
        $t_dni, $t_apellido, $t_nombre, $t_parentesco, $t_tel, $t_email, $t_calle, $t_num, $t_barrio, $t_ocupacion,
        $t_tel, $t_email, $t_calle, $t_num, $t_barrio
    );
    $stmt_tutor->execute();
    $id_tutor = $conn->insert_id;
    $stmt_tutor->close();

    // 2. Insertar o actualizar Estudiante
    $stmt_est = $conn->prepare("INSERT INTO estudiantes (dni, cuil, apellido, nombre, fecha_nacimiento, genero, nacionalidad, id_tutor, obra_social, observaciones_medicas)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE id_estudiante=LAST_INSERT_ID(id_estudiante), id_tutor=?, obra_social=?, observaciones_medicas=?");

    $e_dni = $data['estudiante_dni'] ?? '';
    $e_cuil = $data['estudiante_cuil'] ?? '';
    $e_apellido = $data['estudiante_apellido'] ?? '';
    $e_nombre = $data['estudiante_nombre'] ?? '';
    $e_fnac = $data['estudiante_fecha_nacimiento'] ?? '2010-01-01';
    $e_genero = $data['estudiante_genero'] ?? 'MASCULINO';
    $e_nac = $data['estudiante_nacionalidad'] ?? 'Argentina';
    $e_os = $data['estudiante_obra_social'] ?? 'Ninguna';
    $e_med = $data['estudiante_observaciones_medicas'] ?? '';

    $stmt_est->bind_param("sssssssississ", 
        $e_dni, $e_cuil, $e_apellido, $e_nombre, $e_fnac, $e_genero, $e_nac, $id_tutor, $e_os, $e_med,
        $id_tutor, $e_os, $e_med
    );
    $stmt_est->execute();
    $id_estudiante = $conn->insert_id;
    $stmt_est->close();

    // 3. Buscar ciclo lectivo activo
    $res_ciclo = $conn->query("SELECT id_ciclo FROM ciclos_lectivos WHERE estado = 'ACTIVO' LIMIT 1");
    $ciclo = $res_ciclo->fetch_assoc();
    $id_ciclo = $ciclo['id_ciclo'] ?? 1;

    // 4. Buscar oferta académica coincidente
    $anio_estudio = $data['anio_estudio'] ?? '1ER_ANIO';
    $turno = $data['turno'] ?? 'MANANA';
    
    $stmt_of = $conn->prepare("SELECT id_oferta FROM ofertas_academicas WHERE anio_estudio = ? AND turno = ? LIMIT 1");
    $stmt_of->bind_param("ss", $anio_estudio, $turno);
    $stmt_of->execute();
    $res_of = $stmt_of->get_result();
    $oferta = $res_of->fetch_assoc();
    $id_oferta = $oferta['id_oferta'] ?? 1;
    $stmt_of->close();

    // 5. Generar Solicitud con Expediente Único
    $num_expediente = "EXP-" . date("Y") . "-" . str_pad(mt_rand(1, 99999), 5, "0", STR_PAD_LEFT);
    $tipo_insc = $data['tipo_inscripcion'] ?? 'INGRESO_1ER_ANIO';
    $colegio_origen = $data['colegio_origen'] ?? '';

    $stmt_sol = $conn->prepare("INSERT INTO solicitudes_inscripcion (numero_expediente, id_estudiante, id_ciclo, id_oferta, tipo_inscripcion, colegio_origen, estado_solicitud)
        VALUES (?, ?, ?, ?, ?, ?, 'PENDIENTE')");
    $stmt_sol->bind_param("siiiss", $num_expediente, $id_estudiante, $id_ciclo, $id_oferta, $tipo_insc, $colegio_origen);
    $stmt_sol->execute();
    $id_solicitud = $conn->insert_id;
    $stmt_sol->close();

    // 6. Insertar Requisitos Documentales
    $doc_dni_a = isset($data['doc_dni_alumno']) ? 1 : 0;
    $doc_dni_t = isset($data['doc_dni_tutor']) ? 1 : 0;
    $doc_partida = isset($data['doc_partida']) ? 1 : 0;
    $doc_certificado = isset($data['doc_certificado']) ? 1 : 0;

    $stmt_req = $conn->prepare("INSERT INTO requisitos_documentales (id_solicitud, fotocopia_dni_alumno, fotocopia_dni_tutor, partida_nacimiento, certificado_septimo_o_pase)
        VALUES (?, ?, ?, ?, ?)");
    $stmt_req->bind_param("iiiii", $id_solicitud, $doc_dni_a, $doc_dni_t, $doc_partida, $doc_certificado);
    $stmt_req->execute();
    $stmt_req->close();

    $conn->commit();

    echo json_encode([
        "status" => "success",
        "message" => "Solicitud de inscripción registrada con éxito en MySQL Local.",
        "expediente" => $num_expediente,
        "id_solicitud" => $id_solicitud
    ]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Error al guardar en la base de datos: " . $e->getMessage()
    ]);
}

$conn->close();
?>