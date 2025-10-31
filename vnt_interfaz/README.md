# Sistema de Venta de Boletos con QR

## Descripción
Sistema completo para la venta de boletos con generación automática de códigos QR y control de entrada.

## Características Implementadas

### 1. Compra de Boletos
- **Selección de asientos**: Click en los asientos para agregarlos al carrito
- **Carrito de compras**: Panel lateral que muestra:
  - Asientos seleccionados
  - Categoría y precio de cada asiento
  - Total de la compra
  - Botón para remover asientos individuales
- **Validación**: No permite seleccionar asientos ya vendidos
- **Visualización**: 
  - Asientos disponibles: Color según categoría
  - Asientos seleccionados: Borde verde
  - Asientos vendidos: Color rojo, no clickeables

### 2. Generación de Boletos
Al confirmar la compra:
- Se crea un registro en la tabla `boletos` por cada asiento
- Se genera un **código único alfanumérico** de 16 caracteres
- Se crea automáticamente un **código QR** con ese código
- Los QR se guardan en la carpeta `/boletos_qr/`
- El campo `estatus` se establece en **1** (activo)
- Se muestra un modal con todos los boletos generados y sus QR

### 3. Escaneo y Control de Entrada
Archivo: `escanear_qr.php`

Funcionalidades:
- **Búsqueda de boletos**: Por código QR o manual
- **Información del boleto**:
  - Código único
  - Evento
  - Asiento
  - Categoría
  - Precio
  - Estado (Activo/Usado)
- **Confirmación de entrada**:
  - Botón para confirmar entrada
  - Cambia el `estatus` a **0** (usado)
  - Previene uso duplicado del boleto
- **Validaciones**:
  - Alerta si el boleto ya fue usado
  - Confirmación antes de marcar entrada

## Estructura de Archivos

```
vnt_interfaz/
├── index.php                          # Interfaz principal de venta
├── procesar_compra.php                # Procesa la compra y genera QR
├── obtener_asientos_vendidos.php     # API para obtener asientos vendidos
├── escanear_qr.php                    # Control de entrada con QR
├── composer.json                      # Dependencias de Composer
├── .gitignore                         # Ignora vendor/
├── css/
│   └── carrito.css                    # Estilos del carrito
├── js/
│   └── carrito.js                     # Lógica del carrito
└── vendor/                            # Librerías de Composer (auto-generado)

boletos_qr/                            # Carpeta para almacenar QR generados
└── [CODIGO_UNICO].png                 # Archivos QR
```

## Base de Datos

### Tabla: boletos
Campos utilizados:
- `id_boleto`: ID único del boleto
- `id_evento`: Referencia al evento
- `id_asiento`: Referencia al asiento
- `id_categoria`: Categoría del boleto
- `codigo_unico`: **Código alfanumérico único** (usado para el QR)
- `precio_base`: Precio original
- `descuento_aplicado`: Descuento aplicado (0 por defecto)
- `precio_final`: Precio final pagado
- `estatus`: **1 = Activo, 0 = Usado**

## Flujo de Uso

### Para Vender Boletos:
1. Acceder a `vnt_interfaz/index.php`
2. Seleccionar un evento del dropdown
3. Click en los asientos deseados (se agregan al carrito)
4. Verificar el total en el panel derecho
5. Click en "Pagar"
6. Se generan los boletos con sus códigos QR
7. Se muestra un modal con todos los QR generados

### Para Control de Entrada:
1. Acceder a `vnt_interfaz/escanear_qr.php`
2. Escanear el código QR o ingresar el código manualmente
3. Verificar la información del boleto
4. Si es válido, click en "Confirmar Entrada"
5. El boleto se marca como usado (estatus = 0)

## Tecnologías Utilizadas

- **PHP**: Backend y lógica de negocio
- **MySQL**: Base de datos
- **Composer**: Gestor de dependencias
- **endroid/qr-code**: Generación de códigos QR
- **Bootstrap 5**: Framework CSS
- **JavaScript**: Interactividad del carrito

## Instalación

Las dependencias ya están instaladas. Si necesita reinstalar:

```bash
cd vnt_interfaz
composer install
```

## Seguridad

- Validación de asientos duplicados
- Transacciones SQL para integridad de datos
- Prevención de uso duplicado de boletos
- Códigos únicos generados con `random_bytes()`

## Notas Importantes

1. La carpeta `boletos_qr/` debe tener permisos de escritura
2. Los códigos QR se generan en formato PNG de 300x300px
3. Los asientos vendidos se cargan dinámicamente al abrir la página
4. El sistema previene la venta de asientos duplicados mediante validación en BD
5. Los boletos usados no pueden volver a activarse (operación irreversible)
