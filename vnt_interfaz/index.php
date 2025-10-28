<?php
// Define qué página está activa y la ruta relativa (subir un nivel)
$pagina_actual = 'ventas';
$path_relativo = '../';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Ventas - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        /* === COPIA Y PEGA AQUÍ TODO EL BLOQUE <style> DEL index.php ANTERIOR === */
         :root {
            --primary-color: #B08D57; /* Un tono dorado/café elegante para el teatro */
            --primary-hover: #9c7b4a;
            --sidebar-bg: #2d3436;    /* Gris oscuro casi negro */
            --sidebar-link: #dfe6e9;  /* Texto gris muy claro */
            --sidebar-hover: #4b5457;
            --sidebar-active-text: #ffffff;
            --content-bg: #fdfdfd;   /* Fondo del contenido casi blanco */
        }
        /* ... (resto del CSS) ... */
         body, html { height: 100%; margin: 0; background-color: var(--content-bg); font-family: 'Poppins', sans-serif; }
        .main-container { display: flex; min-height: 100vh; }
        .sidebar { width: 260px; background-color: var(--sidebar-bg); padding: 0; color: var(--sidebar-link); flex-shrink: 0; display: flex; flex-direction: column; box-shadow: 4px 0 10px rgba(0, 0, 0, 0.2); transition: width 0.3s ease; }
        .sidebar h4 { margin-bottom: 0; padding: 1.5rem 1.5rem; background-color: rgba(0, 0, 0, 0.2); border-bottom: 1px solid rgba(255, 255, 255, 0.1); font-weight: 600; color: var(--sidebar-active-text); display: flex; align-items: center; }
        .sidebar h4 i { margin-right: 10px; font-size: 1.4em; color: var(--primary-color); }
        .sidebar .nav-pills { padding: 1.5rem 1rem; }
        .sidebar .nav-link { color: var(--sidebar-link); font-size: 1.05rem; margin-bottom: 0.6rem; display: flex; align-items: center; padding: 0.8rem 1.25rem; border-radius: 8px; transition: background-color 0.2s ease, color 0.2s ease, transform 0.15s ease, box-shadow 0.2s ease; position: relative; overflow: hidden; }
        .sidebar .nav-link i { margin-right: 15px; font-size: 1.3em; width: 28px; transition: color 0.2s ease, transform 0.2s ease; }
        .sidebar .nav-link:not(.active):hover { background-color: var(--sidebar-hover); color: var(--sidebar-active-text); transform: translateX(3px); box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .sidebar .nav-link:not(.active):hover i { color: var(--primary-color); transform: scale(1.1); }
        .sidebar .nav-link.active { background-color: var(--primary-color); color: var(--sidebar-active-text); font-weight: 500; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2); }
        .sidebar .nav-link.active i { color: var(--sidebar-active-text); }
        .content { flex-grow: 1; padding: 2.5rem; height: 100vh; overflow-y: auto; }
        .content h1, .content h2 { color: #333; margin-bottom: 1.5rem; border-bottom: 2px solid var(--primary-color); padding-bottom: 0.75rem; font-weight: 600; }
    </style>
</head>
<body>

<div class="main-container">

   
    <div class="content">
        <h1><i class="bi bi-receipt-cutoff"></i> Gestión de Ventas</h1>
        <p>Aquí podrás ver los reportes de ventas, buscar transacciones, etc.</p>
        </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>