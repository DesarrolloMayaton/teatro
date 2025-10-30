<?php
// promos_api.php
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$DOCROOT = $_SERVER['DOCUMENT_ROOT'] ?? '';
// Ajusta esta ruta si es necesario:
require_once __DIR__ . '/../evt_interfaz/conexion.php'; // <- usa tu conexion.php

// Detectar conexión (mysqli o PDO)
function db_mysqli() {
  foreach (['conn','conexion','mysqli'] as $m) {
    if (isset($GLOBALS[$m]) && $GLOBALS[$m] instanceof mysqli) return $GLOBALS[$m];
  }
  return null;
}
function db_pdo() { return (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) ? $GLOBALS['pdo'] : null; }

function json_out($data, $code=200){
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}
function bad($msg, $code=400){ json_out(['ok'=>false,'error'=>$msg], $code); }

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$q = $_GET;

// Export CSV (GET ?export=1)
if ($method === 'GET' && isset($q['export'])) {
  // Descargar CSV de todas las promociones (join con evento)
  $mysqli = db_mysqli();
  $pdo = db_pdo();
  $sql = "SELECT p.id_promocion, p.id_evento, e.titulo AS evento,
                 p.tipo_boleto, p.precio_base, p.modo, p.valor,
                 p.desde, p.hasta, p.min_cantidad, p.condiciones,
                 p.created_at, p.updated_at
          FROM promocion p
          LEFT JOIN evento e ON e.id_evento = p.id_evento
          ORDER BY p.created_at DESC, p.id_promocion DESC";
  if ($mysqli) {
    $res = $mysqli->query($sql);
    if ($res === false) bad('Error SQL: '.$mysqli->error, 500);
    $rows = [];
    while ($row = $res->fetch_assoc()) $rows[] = $row;
  } else if ($pdo) {
    $st = $pdo->query($sql);
    if (!$st) bad('Error SQL PDO', 500);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
  } else {
    bad('Sin conexión a BD', 500);
  }

  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=historial_promociones.csv');
  $out = fopen('php://output', 'w');
  $headers = ['id_promocion','id_evento','evento','tipo_boleto','precio_base','modo','valor','desde','hasta','min_cantidad','condiciones','created_at','updated_at'];
  fputcsv($out, $headers);
  foreach ($rows as $r) {
    $line = [];
    foreach ($headers as $h) $line[] = $r[$h] ?? '';
    fputcsv($out, $line);
  }
  fclose($out);
  exit;
}

// Listar (GET)
if ($method === 'GET') {
  $mysqli = db_mysqli();
  $pdo = db_pdo();
  $sql = "SELECT p.*, e.titulo AS evento
          FROM promocion p
          LEFT JOIN evento e ON e.id_evento = p.id_evento
          ORDER BY p.desde DESC, p.id_promocion DESC";
  if ($mysqli) {
    $res = $mysqli->query($sql);
    if ($res === false) bad('Error SQL: '.$mysqli->error, 500);
    $rows = [];
    while ($row = $res->fetch_assoc()) $rows[] = $row;
    json_out(['ok'=>true,'items'=>$rows]);
  } else if ($pdo) {
    $st = $pdo->query($sql);
    if (!$st) bad('Error SQL PDO', 500);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    json_out(['ok'=>true,'items'=>$rows]);
  } else {
    bad('Sin conexión a BD', 500);
  }
}

// Leer cuerpo (POST/PUT/DELETE)
$raw = file_get_contents('php://input');
$ctype = $_SERVER['CONTENT_TYPE'] ?? '';
$data = [];
if (stripos($ctype,'application/json') !== false) {
  $tmp = json_decode($raw, true);
  if (is_array($tmp)) $data = $tmp;
} else {
  // x-www-form-urlencoded o multipart
  $data = $_POST;
}

// Validación y normalización común
function normalize($d) {
  return [
    'id_evento'   => isset($d['id_evento']) ? (int)$d['id_evento'] : null,
    'tipo_boleto' => trim($d['tipo_boleto'] ?? ''),
    'precio_base' => (float)($d['precio_base'] ?? 0),
    'modo'        => ($d['modo'] ?? ''),
    'valor'       => (float)($d['valor'] ?? 0),
    'desde'       => substr(($d['desde'] ?? ''),0,10),
    'hasta'       => substr(($d['hasta'] ?? ''),0,10),
    'min_cantidad'=> (int)($d['min_cantidad'] ?? 1),
    'condiciones' => trim($d['condiciones'] ?? ''),
  ];
}
function validate($n, $for_update=false) {
  if (!$for_update) {
    if (!$n['id_evento']) bad('id_evento requerido');
  }
  if ($n['tipo_boleto']==='') bad('tipo_boleto requerido');
  if (!in_array($n['modo'], ['porcentaje','fijo'], true)) bad('modo inválido (porcentaje|fijo)');
  if ($n['valor'] < 0) bad('valor inválido');
  if ($n['precio_base'] < 0) bad('precio_base inválido');
  if (!$n['desde'] || !$n['hasta']) bad('desde/hasta requeridos (YYYY-MM-DD)');
  if ($n['min_cantidad'] < 1) bad('min_cantidad debe ser >= 1');
}

// Crear (POST)
if ($method === 'POST') {
  $n = normalize($data);
  validate($n, false);

  $mysqli = db_mysqli();
  $pdo = db_pdo();
  if ($mysqli) {
    $stmt = $mysqli->prepare("INSERT INTO promocion (id_evento,tipo_boleto,precio_base,modo,valor,desde,hasta,min_cantidad,condiciones) VALUES (?,?,?,?,?,?,?,?,?)");
    if (!$stmt) bad('Prepare error: '.$mysqli->error, 500);
    $stmt->bind_param('isdsdssis',
      $n['id_evento'], $n['tipo_boleto'], $n['precio_base'], $n['modo'], $n['valor'],
      $n['desde'], $n['hasta'], $n['min_cantidad'], $n['condiciones']
    );
    if (!$stmt->execute()) bad('Execute error: '.$stmt->error, 500);
    $id = $stmt->insert_id;
    $stmt->close();
    json_out(['ok'=>true, 'id_promocion'=>$id]);
  } else if ($pdo) {
    $sql = "INSERT INTO promocion (id_evento,tipo_boleto,precio_base,modo,valor,desde,hasta,min_cantidad,condiciones)
            VALUES (:id_evento,:tipo_boleto,:precio_base,:modo,:valor,:desde,:hasta,:min_cantidad,:condiciones)";
    $st = $pdo->prepare($sql);
    $ok = $st->execute([
      ':id_evento'=>$n['id_evento'], ':tipo_boleto'=>$n['tipo_boleto'], ':precio_base'=>$n['precio_base'],
      ':modo'=>$n['modo'], ':valor'=>$n['valor'], ':desde'=>$n['desde'], ':hasta'=>$n['hasta'],
      ':min_cantidad'=>$n['min_cantidad'], ':condiciones'=>$n['condiciones']
    ]);
    if (!$ok) bad('Execute PDO error', 500);
    $id = $pdo->lastInsertId();
    json_out(['ok'=>true, 'id_promocion'=>(int)$id]);
  } else {
    bad('Sin conexión a BD', 500);
  }
}

// Actualizar (PUT)
if ($method === 'PUT') {
  parse_str($_SERVER['QUERY_STRING'] ?? '', $qs);
  $id = isset($qs['id']) ? (int)$qs['id'] : (int)($data['id_promocion'] ?? 0);
  if ($id<=0) bad('id_promocion requerido para actualizar');

  $n = normalize($data);
  validate($n, true);

  $mysqli = db_mysqli();
  $pdo = db_pdo();
  if ($mysqli) {
    $stmt = $mysqli->prepare("UPDATE promocion
      SET id_evento=?, tipo_boleto=?, precio_base=?, modo=?, valor=?, desde=?, hasta=?, min_cantidad=?, condiciones=?
      WHERE id_promocion=?");
    if (!$stmt) bad('Prepare error: '.$mysqli->error, 500);
    $stmt->bind_param('isdsdssisi',
      $n['id_evento'], $n['tipo_boleto'], $n['precio_base'], $n['modo'], $n['valor'],
      $n['desde'], $n['hasta'], $n['min_cantidad'], $n['condiciones'], $id
    );
    if (!$stmt->execute()) bad('Execute error: '.$stmt->error, 500);
    $stmt->close();
    json_out(['ok'=>true]);
  } else if ($pdo) {
    $sql = "UPDATE promocion SET id_evento=:id_evento, tipo_boleto=:tipo_boleto, precio_base=:precio_base,
            modo=:modo, valor=:valor, desde=:desde, hasta=:hasta, min_cantidad=:min_cantidad, condiciones=:condiciones
            WHERE id_promocion=:id";
    $st = $pdo->prepare($sql);
    $ok = $st->execute([
      ':id'=>$id, ':id_evento'=>$n['id_evento'], ':tipo_boleto'=>$n['tipo_boleto'], ':precio_base'=>$n['precio_base'],
      ':modo'=>$n['modo'], ':valor'=>$n['valor'], ':desde'=>$n['desde'], ':hasta'=>$n['hasta'],
      ':min_cantidad'=>$n['min_cantidad'], ':condiciones'=>$n['condiciones']
    ]);
    if (!$ok) bad('Execute PDO error', 500);
    json_out(['ok'=>true]);
  } else {
    bad('Sin conexión a BD', 500);
  }
}

// Eliminar (DELETE)
if ($method === 'DELETE') {
  parse_str($_SERVER['QUERY_STRING'] ?? '', $qs);
  $id = isset($qs['id']) ? (int)$qs['id'] : 0;
  if ($id<=0) bad('id_promocion requerido para eliminar');

  $mysqli = db_mysqli();
  $pdo = db_pdo();
  if ($mysqli) {
    $stmt = $mysqli->prepare("DELETE FROM promocion WHERE id_promocion=?");
    if (!$stmt) bad('Prepare error: '.$mysqli->error, 500);
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) bad('Execute error: '.$stmt->error, 500);
    $stmt->close();
    json_out(['ok'=>true]);
  } else if ($pdo) {
    $st = $pdo->prepare("DELETE FROM promocion WHERE id_promocion=:id");
    $ok = $st->execute([':id'=>$id]);
    if (!$ok) bad('Execute PDO error', 500);
    json_out(['ok'=>true]);
  } else {
    bad('Sin conexión a BD', 500);
  }
}

bad('Método no soportado', 405);
