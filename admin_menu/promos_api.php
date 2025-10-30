<?php
// promos_api.php — CRUD real sobre tabla `promocion` (BD trt_25)
header('Content-Type: application/json; charset=utf-8');

// (Dev) muestra errores de mysqli como excepciones
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// ---- Conexión ----
// Ajusta la ruta si tu conexion.php está en otro directorio:
require_once __DIR__ . '/../evt_interfaz/conexion.php';

// Detecta mysqli o PDO expuestos por conexion.php
$mysqli = null; $pdo = null;
foreach (['conn','conexion','mysqli'] as $m) {
  if (isset($GLOBALS[$m]) && $GLOBALS[$m] instanceof mysqli) { $mysqli = $GLOBALS[$m]; }
}
if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) { $pdo = $GLOBALS['pdo']; }

function respond($ok, $data = []) {
  echo json_encode($ok ? array_merge(['ok'=>true], $data) : array_merge(['ok'=>false], $data), JSON_UNESCAPED_UNICODE);
  exit;
}

function fetch_all_assoc_mysqli($res) {
  $rows = [];
  while ($row = $res->fetch_assoc()) $rows[] = $row;
  return $rows;
}

// JSON body
$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) $body = [];

// Acción
$action = $_GET['action'] ?? 'list';

// ---------- EXPORT CSV ----------
if ($action === 'export') {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=historial_promociones.csv');

  $sql = "SELECT p.*, e.titulo AS evento
          FROM promocion p
          LEFT JOIN evento e ON e.id_evento = p.id_evento
          ORDER BY p.desde ASC, p.id_promocion ASC";

  if ($mysqli) {
    $res = $mysqli->query($sql);
    $out = fopen('php://output', 'w');
    fputcsv($out, ['id_promocion','id_evento','evento','tipo_boleto','precio_base','modo','valor','desde','hasta','min_cantidad','condiciones','created_at','updated_at']);
    while ($row = $res->fetch_assoc()) {
      fputcsv($out, [
        $row['id_promocion'], $row['id_evento'], $row['evento'], $row['tipo_boleto'],
        $row['precio_base'], $row['modo'], $row['valor'], $row['desde'], $row['hasta'],
        $row['min_cantidad'], $row['condiciones'], $row['created_at'], $row['updated_at']
      ]);
    }
    fclose($out); exit;
  } elseif ($pdo) {
    $st = $pdo->query($sql);
    $rows = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
    $out = fopen('php://output', 'w');
    fputcsv($out, ['id_promocion','id_evento','evento','tipo_boleto','precio_base','modo','valor','desde','hasta','min_cantidad','condiciones','created_at','updated_at']);
    foreach ($rows as $row) {
      fputcsv($out, [
        $row['id_promocion'], $row['id_evento'], $row['evento'], $row['tipo_boleto'],
        $row['precio_base'], $row['modo'], $row['valor'], $row['desde'], $row['hasta'],
        $row['min_cantidad'], $row['condiciones'], $row['created_at'], $row['updated_at']
      ]);
    }
    fclose($out); exit;
  } else {
    echo "No DB connection"; exit;
  }
}

// ---------- LIST ----------
if ($action === 'list') {
  $sql = "SELECT p.*, e.titulo AS evento
          FROM promocion p
          LEFT JOIN evento e ON e.id_evento = p.id_evento
          ORDER BY p.desde ASC, p.id_promocion ASC";

  if ($mysqli) {
    $res = $mysqli->query($sql);
    respond(true, ['items'=>fetch_all_assoc_mysqli($res)]);
  } elseif ($pdo) {
    $st = $pdo->query($sql);
    respond(true, ['items'=>$st ? $st->fetchAll(PDO::FETCH_ASSOC) : []]);
  } else {
    respond(false, ['error'=>'No DB connection']);
  }
}

// Campos esperados
function expect_fields($src){
  return [
    'id_evento'    => (int)($src['id_evento']    ?? 0),
    'tipo_boleto'  => trim((string)($src['tipo_boleto'] ?? '')),
    'precio_base'  => (float)($src['precio_base'] ?? 0),
    'modo'         => in_array(($src['modo'] ?? ''), ['porcentaje','fijo'], true) ? $src['modo'] : 'porcentaje',
    'valor'        => (float)($src['valor'] ?? 0),
    'desde'        => (string)($src['desde'] ?? date('Y-m-d')),
    'hasta'        => (string)($src['hasta'] ?? date('Y-m-d')),
    'min_cantidad' => (int)($src['min_cantidad'] ?? 1),
    'condiciones'  => trim((string)($src['condiciones'] ?? ''))
  ];
}

// ---------- CREATE ----------
if ($action === 'create') {
  $f = expect_fields($body);
  if ($f['id_evento']<=0 || $f['tipo_boleto']==='' || $f['precio_base']<=0) {
    respond(false, ['error'=>'Datos incompletos']);
  }

  if ($mysqli) {
    $stmt = $mysqli->prepare(
      "INSERT INTO promocion
       (id_evento,tipo_boleto,precio_base,modo,valor,desde,hasta,min_cantidad,condiciones)
       VALUES (?,?,?,?,?,?,?,?,?)"
    );
    // Tipos: i s d s d s s i s
    $stmt->bind_param(
      "isdsdssis",
      $f['id_evento'],
      $f['tipo_boleto'],
      $f['precio_base'],
      $f['modo'],
      $f['valor'],
      $f['desde'],
      $f['hasta'],
      $f['min_cantidad'],
      $f['condiciones']
    );
    $stmt->execute();
    respond(true, ['id_promocion'=>$stmt->insert_id]);

  } elseif ($pdo) {
    $st = $pdo->prepare(
      "INSERT INTO promocion
       (id_evento,tipo_boleto,precio_base,modo,valor,desde,hasta,min_cantidad,condiciones)
       VALUES (?,?,?,?,?,?,?,?,?)"
    );
    $st->execute([$f['id_evento'],$f['tipo_boleto'],$f['precio_base'],$f['modo'],$f['valor'],$f['desde'],$f['hasta'],$f['min_cantidad'],$f['condiciones']]);
    respond(true, ['id_promocion'=>$pdo->lastInsertId()]);
  } else {
    respond(false, ['error'=>'No DB connection']);
  }
}

// ---------- UPDATE ----------
if ($action === 'update') {
  $id = (int)($_GET['id'] ?? 0);
  if ($id<=0) respond(false, ['error'=>'Id inválido']);
  $f = expect_fields($body);

  if ($mysqli) {
    $stmt = $mysqli->prepare(
      "UPDATE promocion
       SET id_evento=?, tipo_boleto=?, precio_base=?, modo=?, valor=?, desde=?, hasta=?, min_cantidad=?, condiciones=?
       WHERE id_promocion=?"
    );
    // Tipos: i s d s d s s i s | i
    $stmt->bind_param(
      "isdsdssisi",
      $f['id_evento'],
      $f['tipo_boleto'],
      $f['precio_base'],
      $f['modo'],
      $f['valor'],
      $f['desde'],
      $f['hasta'],
      $f['min_cantidad'],
      $f['condiciones'],
      $id
    );
    $stmt->execute();
    respond(true);

  } elseif ($pdo) {
    $st = $pdo->prepare(
      "UPDATE promocion
       SET id_evento=?, tipo_boleto=?, precio_base=?, modo=?, valor=?, desde=?, hasta=?, min_cantidad=?, condiciones=?
       WHERE id_promocion=?"
    );
    $st->execute([$f['id_evento'],$f['tipo_boleto'],$f['precio_base'],$f['modo'],$f['valor'],$f['desde'],$f['hasta'],$f['min_cantidad'],$f['condiciones'],$id]);
    respond(true);
  } else {
    respond(false, ['error'=>'No DB connection']);
  }
}

// ---------- DELETE ----------
if ($action === 'delete') {
  $id = (int)($_GET['id'] ?? 0);
  if ($id<=0) respond(false, ['error'=>'Id inválido']);

  if ($mysqli) {
    $stmt = $mysqli->prepare("DELETE FROM promocion WHERE id_promocion=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    respond(true);

  } elseif ($pdo) {
    $st = $pdo->prepare("DELETE FROM promocion WHERE id_promocion=?");
    $st->execute([$id]);
    respond(true);

  } else {
    respond(false, ['error'=>'No DB connection']);
  }
}

// Acción desconocida
respond(false, ['error'=>'Acción no soportada']);
