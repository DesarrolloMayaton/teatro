# Manual de Uso - Sistema de Venta de Boletos

## Inicio Rápido

### 1. Verificar Instalación
Accede a: `http://localhost/teatro/vnt_interfaz/test_qr.php`

Esto verificará que:
- Composer está instalado
- La librería QR funciona correctamente
- La carpeta boletos_qr tiene permisos

### 2. Acceder al Sistema
URL: `http://localhost/teatro/vnt_interfaz/index.php`

## Guía Paso a Paso

### A. Venta de Boletos

#### Paso 1: Seleccionar Evento
1. En el panel derecho, usa el dropdown "Selecciona un Evento"
2. Elige el evento deseado
3. El mapa de asientos se cargará automáticamente

#### Paso 2: Seleccionar Asientos
1. **Click en un asiento** para agregarlo al carrito
   - El asiento se marcará con borde verde
   - Aparecerá en el carrito con su precio
2. **Click nuevamente** para removerlo del carrito
3. Los asientos en **rojo** ya están vendidos (no clickeables)

#### Paso 3: Revisar Carrito
El panel derecho muestra:
```
┌─────────────────────────────┐
│ Carrito de Compras          │
├─────────────────────────────┤
│ A1                      [X] │
│ VIP                         │
│ $150.00                     │
├─────────────────────────────┤
│ A2                      [X] │
│ VIP                         │
│ $150.00                     │
├─────────────────────────────┤
│ Total:            $300.00   │
└─────────────────────────────┘
```

#### Paso 4: Procesar Pago
1. Click en el botón **"Pagar"**
2. El sistema procesará la compra
3. Se mostrará un modal con los boletos generados
4. Cada boleto incluye:
   - Código QR
   - Código único alfanumérico
   - Información del asiento

#### Paso 5: Guardar/Imprimir Boletos
- Los QR se guardan automáticamente en `/boletos_qr/`
- Puedes descargar o imprimir desde el modal
- Los códigos QR son únicos e irrepetibles

### B. Control de Entrada

#### Acceso
URL: `http://localhost/teatro/vnt_interfaz/escanear_qr.php`

#### Método 1: Escaneo Manual
1. Ingresa el código del boleto en el campo de búsqueda
2. Click en "Buscar"
3. Se mostrará la información del boleto

#### Método 2: Escaneo con Lector QR
1. Usa un lector de códigos QR (app móvil, escáner físico)
2. Escanea el código QR del boleto
3. El código se ingresará automáticamente
4. Se mostrará la información del boleto

#### Validación del Boleto
El sistema muestra:
```
┌─────────────────────────────────┐
│ ✓ BOLETO VÁLIDO                 │
├─────────────────────────────────┤
│ [Imagen del QR]                 │
├─────────────────────────────────┤
│ Código: ABC123DEF456            │
│ Evento: Concierto Rock 2025     │
│ Asiento: A15                    │
│ Categoría: VIP                  │
│ Precio: $150.00                 │
│ Estado: Activo                  │
├─────────────────────────────────┤
│ [Confirmar Entrada]             │
└─────────────────────────────────┘
```

#### Confirmar Entrada
1. Verifica que la información sea correcta
2. Click en **"Confirmar Entrada"**
3. Confirma la acción en el diálogo
4. El boleto se marca como **USADO**
5. Ya no podrá usarse nuevamente

#### Boleto Ya Usado
Si el boleto ya fue usado:
```
┌─────────────────────────────────┐
│ ✗ BOLETO USADO                  │
├─────────────────────────────────┤
│ ⚠ Este boleto ya fue utilizado  │
└─────────────────────────────────┘
```

## Casos de Uso Comunes

### Caso 1: Venta Individual
1. Cliente quiere 1 boleto
2. Selecciona 1 asiento
3. Paga
4. Recibe 1 QR

### Caso 2: Venta Grupal
1. Cliente quiere 5 boletos
2. Selecciona 5 asientos
3. Paga
4. Recibe 5 QR (uno por asiento)

### Caso 3: Cliente Cambia de Opinión
1. Cliente selecciona asientos A1, A2, A3
2. Decide no querer A2
3. Click en [X] junto a A2 en el carrito
4. A2 se remueve, total se actualiza

### Caso 4: Asiento Ya Vendido
1. Cliente intenta seleccionar asiento B5
2. B5 está en rojo (vendido)
3. No puede seleccionarlo
4. Debe elegir otro asiento

### Caso 5: Entrada Duplicada (Intento de Fraude)
1. Persona intenta entrar con boleto ya usado
2. Sistema muestra "BOLETO USADO"
3. No permite confirmar entrada
4. Personal de seguridad puede actuar

## Solución de Problemas

### Problema: No se generan los QR
**Solución:**
1. Verifica que la carpeta `boletos_qr/` exista
2. Verifica permisos de escritura: `chmod 777 boletos_qr/`
3. Ejecuta `test_qr.php` para diagnosticar

### Problema: Asientos no se marcan como vendidos
**Solución:**
1. Verifica la conexión a la base de datos
2. Revisa que la tabla `boletos` tenga datos
3. Refresca la página (F5)

### Problema: Error al procesar compra
**Solución:**
1. Verifica que Composer esté instalado
2. Ejecuta: `composer install` en la carpeta vnt_interfaz
3. Verifica la conexión a MySQL

### Problema: No se puede confirmar entrada
**Solución:**
1. Verifica que el código sea correcto
2. Verifica que el boleto exista en la BD
3. Si ya fue usado, no se puede reactivar

## Características de Seguridad

### Prevención de Fraude
- ✓ Códigos únicos generados con criptografía segura
- ✓ Validación en base de datos
- ✓ Imposible duplicar boletos
- ✓ Registro de uso irreversible

### Integridad de Datos
- ✓ Transacciones SQL (rollback en caso de error)
- ✓ Validación de asientos duplicados
- ✓ Verificación de disponibilidad en tiempo real

### Auditoría
- ✓ Cada boleto tiene registro en BD
- ✓ Estado del boleto (activo/usado)
- ✓ Trazabilidad completa

## Mantenimiento

### Limpieza de QR Antiguos
Los archivos QR se acumulan en `/boletos_qr/`. Para limpiar:

```bash
# Eliminar QR de eventos finalizados
# (Hacer manualmente o crear script)
```

### Respaldo de Base de Datos
Respaldar regularmente la tabla `boletos`:

```sql
-- Exportar boletos
SELECT * FROM boletos 
WHERE id_evento = [ID_EVENTO]
INTO OUTFILE '/backup/boletos.csv';
```

## Contacto y Soporte

Para problemas técnicos:
1. Revisa este manual
2. Ejecuta `test_qr.php` para diagnóstico
3. Verifica logs de PHP y MySQL
4. Contacta al administrador del sistema
