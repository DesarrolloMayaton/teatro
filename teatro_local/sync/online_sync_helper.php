<?php
/**
 * Helper de Sincronización Online
 * ================================
 * Funciones para replicar automáticamente eventos, funciones, categorías,
 * boletos e imágenes de la base de datos local (trt_25) a la base de datos
 * online (trt_25_online) con imágenes y QR como BLOB.
 *
 * Uso: incluir en procesar_evento.php, procesar_compra.php, etc.
 */

if (!function_exists('getOnlineSyncConnection')) {
    /**
     * Obtiene la conexión a la base de datos online.
     * Si la conexión falla, devuelve null y NO interrumpe la operación local.
     */
    function getOnlineSyncConnection() {
        static $conn_online = null;
        if ($conn_online !== null) return $conn_online;

        // Suprimir errores para no romper la lógica local si online no está disponible
        @mysqli_report(MYSQLI_REPORT_OFF);
        try {
            $c = @new mysqli('localhost', 'root', '', 'trt_25_online');
            if ($c->connect_error) return null;
            $c->set_charset('utf8mb4');
            $conn_online = $c;
            return $conn_online;
        } catch (Throwable $e) {
            error_log("[OnlineSync] No se pudo conectar a trt_25_online: " . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('replicarEventoAOnline')) {
    /**
     * Replica un evento (con imagen, funciones, categorías y mapa) a la base online.
     *
     * @param mysqli $conn_local  Conexión local activa.
     * @param int    $id_evento_local  ID del evento recién creado/actualizado en local.
     * @return bool  true si se replicó correctamente, false en caso de error (no fatal).
     */
    function replicarEventoAOnline($conn_local, $id_evento_local) {
        $conn_online = getOnlineSyncConnection();
        if (!$conn_online) return false;

        try {
            // 1. Obtener datos del evento desde local
            $stmt = $conn_local->prepare("
                SELECT id_evento, titulo, descripcion, tipo, imagen, mapa_json, finalizado
                FROM evento WHERE id_evento = ? LIMIT 1
            ");
            $stmt->bind_param('i', $id_evento_local);
            $stmt->execute();
            $evento = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$evento) return false;

            // 2. Leer la imagen como BLOB desde la ruta local
            $imagen_blob = null;
            $imagen_mime = null;
            if (!empty($evento['imagen'])) {
                // La ruta es relativa a evt_interfaz (ej: imagenes/evt_xxx.jpg)
                $rutas_posibles = [
                    __DIR__ . '/../evt_interfaz/' . $evento['imagen'],
                    __DIR__ . '/../' . $evento['imagen'],
                    $evento['imagen'],
                ];
                foreach ($rutas_posibles as $ruta) {
                    if (file_exists($ruta) && is_file($ruta)) {
                        $imagen_blob = file_get_contents($ruta);
                        $info = getimagesize($ruta);
                        $imagen_mime = $info && isset($info['mime']) ? $info['mime'] : 'image/jpeg';
                        break;
                    }
                }
            }

            // 3. Verificar si ya existe en online
            $stmt = $conn_online->prepare("SELECT id_evento FROM evento WHERE id_evento_local = ? LIMIT 1");
            $stmt->bind_param('i', $id_evento_local);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($row) {
                $id_evento_online = (int)$row['id_evento'];
                if ($imagen_blob !== null) {
                    $stmt = $conn_online->prepare("
                        UPDATE evento SET titulo=?, descripcion=?, tipo=?, imagen=?, imagen_mime=?, mapa_json=?, finalizado=?
                        WHERE id_evento = ?
                    ");
                    $null = null;
                    $stmt->bind_param('ssisssii',
                        $evento['titulo'], $evento['descripcion'], $evento['tipo'],
                        $null, $imagen_mime, $evento['mapa_json'], $evento['finalizado'], $id_evento_online
                    );
                    $stmt->send_long_data(3, $imagen_blob);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    $stmt = $conn_online->prepare("
                        UPDATE evento SET titulo=?, descripcion=?, tipo=?, mapa_json=?, finalizado=?
                        WHERE id_evento = ?
                    ");
                    $stmt->bind_param('ssisii',
                        $evento['titulo'], $evento['descripcion'], $evento['tipo'],
                        $evento['mapa_json'], $evento['finalizado'], $id_evento_online
                    );
                    $stmt->execute();
                    $stmt->close();
                }
            } else {
                // INSERT
                $stmt = $conn_online->prepare("
                    INSERT INTO evento (titulo, descripcion, tipo, imagen, imagen_mime, mapa_json, finalizado, id_evento_local)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $null = null;
                $stmt->bind_param('ssisssii',
                    $evento['titulo'], $evento['descripcion'], $evento['tipo'],
                    $null, $imagen_mime, $evento['mapa_json'], $evento['finalizado'], $id_evento_local
                );
                if ($imagen_blob !== null) $stmt->send_long_data(3, $imagen_blob);
                $stmt->execute();
                $id_evento_online = $stmt->insert_id;
                $stmt->close();
            }

            // 4. Replicar funciones
            replicarFuncionesAOnline($conn_local, $conn_online, $id_evento_local, $id_evento_online);

            // 5. Replicar categorías
            replicarCategoriasAOnline($conn_local, $conn_online, $id_evento_local, $id_evento_online);

            return true;
        } catch (Throwable $e) {
            error_log("[OnlineSync] Error replicando evento $id_evento_local: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('replicarFuncionesAOnline')) {
    function replicarFuncionesAOnline($conn_local, $conn_online, $id_evento_local, $id_evento_online) {
        $res = $conn_local->query("SELECT id_funcion, fecha_hora, estado FROM funciones WHERE id_evento = $id_evento_local");
        if (!$res) return;
        while ($f = $res->fetch_assoc()) {
            $check = $conn_online->prepare("SELECT id_funcion FROM funciones WHERE id_funcion_local = ?");
            $check->bind_param('i', $f['id_funcion']);
            $check->execute();
            $exists = $check->get_result()->fetch_assoc();
            $check->close();

            if ($exists) {
                $u = $conn_online->prepare("UPDATE funciones SET fecha_hora=?, estado=? WHERE id_funcion_local=?");
                $u->bind_param('sii', $f['fecha_hora'], $f['estado'], $f['id_funcion']);
                $u->execute();
                $u->close();
            } else {
                $i = $conn_online->prepare("INSERT INTO funciones (id_evento, fecha_hora, estado, id_funcion_local) VALUES (?, ?, ?, ?)");
                $i->bind_param('isii', $id_evento_online, $f['fecha_hora'], $f['estado'], $f['id_funcion']);
                $i->execute();
                $i->close();
            }
        }
    }
}

if (!function_exists('replicarCategoriasAOnline')) {
    function replicarCategoriasAOnline($conn_local, $conn_online, $id_evento_local, $id_evento_online) {
        $res = $conn_local->query("SELECT id_categoria, nombre_categoria, precio, color FROM categorias WHERE id_evento = $id_evento_local");
        if (!$res) return;
        while ($c = $res->fetch_assoc()) {
            $check = $conn_online->prepare("SELECT id_categoria FROM categorias WHERE id_categoria_local = ?");
            $check->bind_param('i', $c['id_categoria']);
            $check->execute();
            $exists = $check->get_result()->fetch_assoc();
            $check->close();

            if ($exists) {
                $u = $conn_online->prepare("UPDATE categorias SET nombre_categoria=?, precio=?, color=? WHERE id_categoria_local=?");
                $u->bind_param('sdsi', $c['nombre_categoria'], $c['precio'], $c['color'], $c['id_categoria']);
                $u->execute();
                $u->close();
            } else {
                $i = $conn_online->prepare("INSERT INTO categorias (id_evento, nombre_categoria, precio, color, id_categoria_local) VALUES (?, ?, ?, ?, ?)");
                $i->bind_param('isdsi', $id_evento_online, $c['nombre_categoria'], $c['precio'], $c['color'], $c['id_categoria']);
                $i->execute();
                $i->close();
            }
        }
    }
}

if (!function_exists('replicarBoletoAOnline')) {
    /**
     * Replica un boleto recién vendido (con QR) a la base online.
     */
    function replicarBoletoAOnline($conn_local, $id_boleto_local) {
        $conn_online = getOnlineSyncConnection();
        if (!$conn_online) return false;

        try {
            $stmt = $conn_local->prepare("
                SELECT b.*, a.codigo_asiento, a.fila, a.numero
                FROM boletos b
                LEFT JOIN asientos a ON b.id_asiento = a.id_asiento
                WHERE b.id_boleto = ? LIMIT 1
            ");
            $stmt->bind_param('i', $id_boleto_local);
            $stmt->execute();
            $boleto = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$boleto) return false;

            // Asegurar asiento en online
            $id_asiento_online = asegurarAsientoOnline($conn_online, $boleto);

            // Obtener id_evento e id_categoria en online via id_local
            $id_evento_online = obtenerIdLocalAOnline($conn_online, 'evento', 'id_evento_local', $boleto['id_evento']);
            $id_categoria_online = obtenerIdLocalAOnline($conn_online, 'categorias', 'id_categoria_local', $boleto['id_categoria']);
            $id_funcion_online = $boleto['id_funcion']
                ? obtenerIdLocalAOnline($conn_online, 'funciones', 'id_funcion_local', $boleto['id_funcion'])
                : null;

            if (!$id_evento_online || !$id_categoria_online || !$id_asiento_online) return false;

            // Leer QR como BLOB si existe
            $qr_blob = null;
            $rutas_qr = [
                __DIR__ . '/../boletos_qr/' . $boleto['codigo_unico'] . '.png',
                __DIR__ . '/../qr/' . $boleto['codigo_unico'] . '.png',
            ];
            foreach ($rutas_qr as $r) {
                if (file_exists($r)) {
                    $qr_blob = file_get_contents($r);
                    break;
                }
            }

            $check = $conn_online->prepare("SELECT id_boleto FROM boletos WHERE id_boleto_local = ?");
            $check->bind_param('i', $id_boleto_local);
            $check->execute();
            $exists = $check->get_result()->fetch_assoc();
            $check->close();

            if ($exists) {
                $u = $conn_online->prepare("UPDATE boletos SET qr_code=?, estatus=?, precio_final=? WHERE id_boleto_local=?");
                $null = null;
                $estatus = (int)$boleto['estatus'];
                $precio_final = (float)$boleto['precio_final'];
                $u->bind_param('sidi', $null, $estatus, $precio_final, $id_boleto_local);
                if ($qr_blob !== null) $u->send_long_data(0, $qr_blob);
                $u->execute();
                $u->close();
            } else {
                $i = $conn_online->prepare("
                    INSERT INTO boletos (id_evento, id_funcion, id_asiento, id_categoria, codigo_unico, qr_code, precio_base, precio_final, tipo_boleto, estatus, id_boleto_local)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $null = null;
                $tipo = isset($boleto['tipo_boleto']) ? $boleto['tipo_boleto'] : 'adulto';
                $estatus = (int)$boleto['estatus'];
                $precio_base = (float)($boleto['precio_base'] ?? $boleto['precio_final']);
                $precio_final = (float)$boleto['precio_final'];
                $i->bind_param('iiiissddsii',
                    $id_evento_online, $id_funcion_online, $id_asiento_online, $id_categoria_online,
                    $boleto['codigo_unico'], $null,
                    $precio_base, $precio_final, $tipo, $estatus, $id_boleto_local
                );
                if ($qr_blob !== null) $i->send_long_data(5, $qr_blob);
                $i->execute();
                $i->close();
            }
            return true;
        } catch (Throwable $e) {
            error_log("[OnlineSync] Error replicando boleto $id_boleto_local: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('asegurarAsientoOnline')) {
    function asegurarAsientoOnline($conn_online, $boleto) {
        $stmt = $conn_online->prepare("SELECT id_asiento FROM asientos WHERE id_asiento_local = ? LIMIT 1");
        $stmt->bind_param('i', $boleto['id_asiento']);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) return (int)$row['id_asiento'];

        $codigo = $boleto['codigo_asiento'] ?? ('ASIENTO_' . $boleto['id_asiento']);
        $fila = $boleto['fila'] ?? null;
        $numero = isset($boleto['numero']) ? (int)$boleto['numero'] : null;

        $i = $conn_online->prepare("INSERT INTO asientos (codigo_asiento, fila, numero, id_asiento_local) VALUES (?, ?, ?, ?)");
        $i->bind_param('ssii', $codigo, $fila, $numero, $boleto['id_asiento']);
        @$i->execute();
        $id = $i->insert_id;
        $i->close();
        if ($id) return $id;

        // Si falló por unique de codigo_asiento, recuperar
        $r = $conn_online->prepare("SELECT id_asiento FROM asientos WHERE codigo_asiento = ? LIMIT 1");
        $r->bind_param('s', $codigo);
        $r->execute();
        $row = $r->get_result()->fetch_assoc();
        $r->close();
        return $row ? (int)$row['id_asiento'] : null;
    }
}

if (!function_exists('obtenerIdLocalAOnline')) {
    function obtenerIdLocalAOnline($conn_online, $tabla, $columna_local, $id_local) {
        $col_pk = [
            'evento' => 'id_evento',
            'funciones' => 'id_funcion',
            'categorias' => 'id_categoria',
        ][$tabla] ?? 'id';
        $stmt = $conn_online->prepare("SELECT $col_pk FROM $tabla WHERE $columna_local = ? LIMIT 1");
        $stmt->bind_param('i', $id_local);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? (int)$row[$col_pk] : null;
    }
}
