<?php
// 1. CONEXIÓN A LA BD
include "../conexion.php"; // Ajusta la ruta si es necesario

// 2. OBTENER EVENTOS ACTIVOS CON SU PRÓXIMA FUNCIÓN
// Seleccionamos eventos activos (finalizado = 0)
// Usamos una subconsulta para encontrar la fecha de la función MÁS PRÓXIMA en el futuro
$query = "
    SELECT 
        e.*, 
        (SELECT MIN(f.fecha_hora) 
         FROM funciones f 
         WHERE f.id_evento = e.id_evento AND f.fecha_hora >= NOW()) AS proxima_funcion_fecha
    FROM evento e
    WHERE e.finalizado = 0 
    HAVING proxima_funcion_fecha IS NOT NULL -- Solo eventos con funciones futuras
    ORDER BY proxima_funcion_fecha ASC;      -- Ordenar por la próxima función
";

$resultado = $conn->query($query);

$eventos = [];
if ($resultado && $resultado->num_rows > 0) {
    // Guardamos todos los eventos en un array
    $eventos = $resultado->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cartelera Próximos Eventos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        body {
            background-color: #eef2f7; /* Un fondo gris claro suave */
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 95vh; /* Ajustado ligeramente */
            padding: 15px; /* Añadir padding por si acaso */
        }

        .cartelera-container {
            position: relative;
            max-width: 380px; /* Un poco más estrecho para mejor estética */
            width: 100%;
        }

        /* La tarjeta en sí (generada por JS) */
        .card-link {
            text-decoration: none;
            color: inherit;
            display: block;
            transition: transform 0.25s ease, box-shadow 0.25s ease;
             border-radius: 12px; /* Redondear enlace para que sombra coincida */
             overflow: hidden; /* Asegurar que la imagen no se salga */
        }
        
        .card-link:hover {
            transform: translateY(-5px); /* Elevar ligeramente */
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.18);
        }

        .card {
             border: none; /* Quitar borde por defecto */
             border-radius: 12px; /* Bordes redondeados */
        }

        .image-container {
            height: 500px; /* Altura fija para la imagen */
            background-color: #f8f9fa; /* Color de fondo si la imagen no llena */
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden; /* Asegura que nada se salga */
        }

        .card-img-top {
             width: 100%;
             height: 100%;
             object-fit: cover; /* Cambiado a cover para llenar, ajustar si se ve mal */
             /* Prueba con 'contain' si 'cover' sigue cortando mucho: */
             /* object-fit: contain; */
             transition: transform 0.3s ease; /* Transición suave para zoom */
        }
        
         .card-link:hover .card-img-top {
             transform: scale(1.05); /* Ligero zoom a la imagen en hover */
         }


        .card-body {
            padding: 1.5rem; /* Más padding */
        }
        
        .card-title {
            font-weight: 600; /* Título más grueso */
            margin-bottom: 0.75rem;
        }
        
        .card-text {
            font-size: 1rem; /* Tamaño de texto para la fecha */
        }

        /* Botones de navegación */
        .nav-btn {
            position: absolute;
            top: 40%; /* Subir un poco los botones */
            transform: translateY(-50%);
            z-index: 10;
            background-color: rgba(44, 62, 80, 0.6); /* Color del menú lateral semi-transparente */
            color: white;
            border: none;
            border-radius: 50%;
            width: 45px; /* Ligeramente más pequeños */
            height: 45px;
            font-size: 1.3rem;
            line-height: 1;
            display: none; 
            opacity: 0.8;
            transition: background-color 0.2s ease, opacity 0.2s ease;
        }
        
        .nav-btn:hover {
            background-color: rgba(44, 62, 80, 0.9);
            opacity: 1;
        }

        #btn-prev { left: -22px; } /* Ajustar posición */
        #btn-next { right: -22px; }

         /* Estilo para el mensaje de no eventos */
        .no-eventos-card {
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }
    </style>
</head>
<body>

    <div class="cartelera-container">
        
        <div id="evento-card-container">
            </div>

        <button id="btn-prev" class="nav-btn" title="Anterior"><i class="bi bi-chevron-left"></i></button>
        <button id="btn-next" class="nav-btn" title="Siguiente"><i class="bi bi-chevron-right"></i></button>

    </div>

    <script>
        // 3. DATOS DE PHP A JAVASCRIPT
        const eventos = <?php echo json_encode($eventos); ?>;
        let currentIndex = 0;

        // 4. ELEMENTOS DEL DOM
        const cardContainer = document.getElementById('evento-card-container');
        const btnPrev = document.getElementById('btn-prev');
        const btnNext = document.getElementById('btn-next');

        // 5. FUNCIÓN PARA MOSTRAR EL EVENTO
        function mostrarEvento(index) {
            if (!eventos || eventos.length === 0) {
                cardContainer.innerHTML = `
                    <div class="card no-eventos-card text-center">
                        <div class="card-body" style="padding: 50px;">
                            <h4 class="card-title text-muted">Próximamente</h4>
                            <p class="card-text text-secondary">No hay eventos activos en cartelera en este momento.</p>
                             <i class="bi bi-calendar-x" style="font-size: 3rem; color: #adb5bd;"></i>
                        </div>
                    </div>`;
                // Ocultar botones si no hay eventos
                 btnPrev.style.display = 'none';
                 btnNext.style.display = 'none';
                return;
            }

            // Seleccionamos el evento actual
            const evento = eventos[index];
            
            // Usamos la 'proxima_funcion_fecha' que calculó PHP
            const fechaProximaFuncion = new Date(evento.proxima_funcion_fecha);
            const fechaFormateada = fechaProximaFuncion.toLocaleString('es-ES', {
                weekday: 'long', // 'martes'
                day: 'numeric',   // '28'
                month: 'long',   // 'octubre'
                hour: 'numeric',  // '09'
                minute: '2-digit', // '20'
                hour12: true      // AM/PM
            });
            
            // Ruta de la imagen (relativa a evt_interfaz)
            const rutaImagen = evento.imagen ? `../evt_interfaz/${evento.imagen}` : 'ruta/a/imagen/placeholder.jpg'; // Añadir placeholder si no hay imagen

            // Enlace a la venta
            const enlaceVenta = `../vnt_interfaz/index.php?id_evento=${evento.id_evento}`;

            // Creamos el HTML de la tarjeta
            const cardHTML = `
                <a href="${enlaceVenta}" class="card-link" title="Ver detalles de ${evento.titulo}">
                    <div class="card shadow-sm overflow-hidden">
                         <div class="image-container">
                              <img src="${rutaImagen}" class="card-img-top" alt="${evento.titulo}">
                         </div>
                        <div class="card-body text-center">
                            <h4 class="card-title mb-2">${evento.titulo}</h4>
                            <p class="card-text text-danger fw-bold">
                                <i class="bi bi-calendar-event"></i> Próxima función:<br> ${fechaFormateada}
                            </p>
                        </div>
                    </div>
                </a>`;
            
            cardContainer.innerHTML = cardHTML;

             // Mostrar botones SOLO si hay más de 1 evento
             if (eventos.length > 1) {
                btnPrev.style.display = 'block';
                btnNext.style.display = 'block';
             } else {
                 btnPrev.style.display = 'none';
                 btnNext.style.display = 'none';
             }
        }

        // 6. LÓGICA DE INICIALIZACIÓN
        document.addEventListener('DOMContentLoaded', () => {
            mostrarEvento(currentIndex);
        });

        // 7. EVENT LISTENERS PARA LOS BOTONES
        btnNext.addEventListener('click', () => {
            currentIndex++;
            if (currentIndex >= eventos.length) {
                currentIndex = 0; // Vuelve al inicio
            }
            mostrarEvento(currentIndex);
        });

        btnPrev.addEventListener('click', () => {
            currentIndex--;
            if (currentIndex < 0) {
                currentIndex = eventos.length - 1; // Va al final
            }
            mostrarEvento(currentIndex);
        });

    </script>
</body>
</html>