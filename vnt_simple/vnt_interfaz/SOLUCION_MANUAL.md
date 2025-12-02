# Solución Manual - Error de Índice Único

## El Problema
Al intentar comprar un asiento que fue cancelado, aparece el error:
```
Duplicate entry '8-451' for key 'boletos.idx_evento_asiento'
```

Esto ocurre porque existe un índice único que impide vender el mismo asiento dos veces, incluso si el boleto anterior fue cancelado.

## Solución Paso a Paso

### Paso 0: Agregar la columna estatus (si no existe)

Primero verifica si la columna existe:

```sql
SHOW COLUMNS FROM boletos LIKE 'estatus';
```

Si no aparece ningún resultado, agrégala:

```sql
ALTER TABLE boletos 
ADD COLUMN estatus TINYINT(1) DEFAULT 1 
COMMENT '1=Activo, 0=Usado, 2=Cancelado';
```

Actualiza los boletos existentes:

```sql
UPDATE boletos SET estatus = 1 WHERE estatus IS NULL;
```

### Paso 1: Identificar las restricciones

Ejecuta esta consulta en phpMyAdmin o tu cliente MySQL:

```sql
SELECT CONSTRAINT_NAME, CONSTRAINT_TYPE
FROM information_schema.TABLE_CONSTRAINTS 
WHERE TABLE_SCHEMA = 'trt_25' 
AND TABLE_NAME = 'boletos';
```

### Paso 2: Ver los índices actuales

```sql
SHOW INDEX FROM boletos;
```

Busca el índice llamado `idx_evento_asiento` y anota si es UNIQUE.

### Paso 3: Eliminar el índice único

**Opción A - Si NO hay clave foránea asociada:**
```sql
ALTER TABLE boletos DROP INDEX idx_evento_asiento;
```

**Opción B - Si HAY una clave foránea (error: "needed in a foreign key constraint"):**

Primero, identifica el nombre de la restricción:
```sql
SELECT CONSTRAINT_NAME 
FROM information_schema.KEY_COLUMN_USAGE 
WHERE TABLE_SCHEMA = 'trt_25' 
AND TABLE_NAME = 'boletos' 
AND CONSTRAINT_NAME != 'PRIMARY';
```

Luego elimina la restricción (reemplaza `nombre_de_la_restriccion`):
```sql
ALTER TABLE boletos DROP FOREIGN KEY nombre_de_la_restriccion;
```

Ahora sí puedes eliminar el índice:
```sql
ALTER TABLE boletos DROP INDEX idx_evento_asiento;
```

### Paso 4: Crear un índice optimizado (opcional pero recomendado)

```sql
CREATE INDEX idx_evento_asiento_estatus ON boletos(id_evento, id_asiento, estatus);
```

Este índice mejora el rendimiento sin bloquear las ventas de asientos cancelados.

### Paso 5: Verificar que funcionó

```sql
SHOW INDEX FROM boletos WHERE Key_name = 'idx_evento_asiento';
```

Si no devuelve resultados, ¡el índice fue eliminado correctamente!

## Alternativa: Modificar el Índice en lugar de Eliminarlo

Si prefieres mantener algún tipo de restricción, puedes convertir el índice único en uno compuesto que incluya el estatus:

```sql
-- Eliminar el índice único actual
ALTER TABLE boletos DROP INDEX idx_evento_asiento;

-- Crear un índice único que incluya el estatus
-- Esto permite múltiples registros del mismo asiento si tienen diferente estatus
CREATE UNIQUE INDEX idx_evento_asiento_activo 
ON boletos(id_evento, id_asiento, estatus);
```

**Nota:** Esta alternativa es más restrictiva y podría causar problemas si necesitas múltiples boletos activos del mismo asiento (aunque esto no debería ocurrir en operación normal).

## Verificación Final

Después de aplicar los cambios:

1. Intenta comprar un boleto
2. Cancela ese boleto
3. Intenta comprar el mismo asiento nuevamente
4. Debería funcionar sin errores

## ¿Necesitas Ayuda?

Si sigues teniendo problemas, ejecuta estas consultas y comparte los resultados:

```sql
-- Ver estructura de la tabla
DESCRIBE boletos;

-- Ver todos los índices
SHOW INDEX FROM boletos;

-- Ver todas las restricciones
SELECT * FROM information_schema.TABLE_CONSTRAINTS 
WHERE TABLE_SCHEMA = 'trt_25' AND TABLE_NAME = 'boletos';

-- Ver claves foráneas
SELECT * FROM information_schema.KEY_COLUMN_USAGE 
WHERE TABLE_SCHEMA = 'trt_25' AND TABLE_NAME = 'boletos';
```
