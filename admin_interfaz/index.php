<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<title>Administración - Teatro</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<base href="/teatro/">

<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
  :root{
    --aside:#2c3e50; --aside-2:#243445; --brand:#3498db; --bg:#f4f7f6; --paper:#fff; --bd:#e6ebf0; --ink:#2c3e50;
    --rad:14px; --shadow:0 10px 30px rgba(0,0,0,.08); --t:.25s ease;
  }
  *{box-sizing:border-box}
  html, body{
    margin:0; background:var(--bg); color:var(--ink);
    font:400 15px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;
    display:flex; min-height:100vh; overflow:hidden;
  }
  
  /* Sidebar (Menú lateral) */
  aside{
    width:230px; background:var(--aside); color:#ecf0f1;
    display:flex; flex-direction:column;
    border-right:1px solid rgba(255,255,255,.08);
    flex-shrink: 0; /* Evita que se encoja */
  }
  .brand{display:flex;gap:10px;align-items:center;padding:16px 18px;border-bottom:1px solid rgba(255,255,255,.08)}
  .brand i{font-size:22px}
  nav{padding:8px 0;overflow:auto}
  
  a.link{
    display:flex;align-items:center;gap:12px;color:#ecf0f1;
    text-decoration:none;padding:14px 18px;
    border-left:4px solid transparent;cursor:pointer
  }
  a.link:hover{background:#3498db22}
  
  /* Puedes manejar la clase .active con JS si lo deseas, 
     pero para iframes es más complejo. Lo dejamos simple. */
  a.link.active{ 
    background:#f4f7f6;color:var(--aside);
    border-left-color:var(--brand);font-weight:600
  }
  a.link i{min-width:22px;font-size:18px}
  
  /* Main (Contenedor para el iframe) */
  main{
    flex:1; display:flex; flex-direction:column;
    min-width:0; height: 100vh;
  }
  header{
    display:flex;align-items:center;padding:14px 18px;
    background:var(--paper);box-shadow:var(--shadow);
    flex-shrink: 0; /* El header no se encoge */
  }
  
  /* El iframe que cargará el contenido */
  .content-frame {
    flex-grow: 1; /* Ocupa todo el espacio sobrante */
    border: none;
    width: 100%;
    height: 100%;
  }
</style>
</head>
<body>
  
  <aside>
    <div class="brand"><i class="bi bi-gear-fill"></i><strong>Administración</strong></div>
    <nav id="menu">
      
      <a href="admin_menu/inicio.php" class="link" target="iframe_contenido">
        <i class="bi bi-house-door-fill"></i>Inicio
      </a>
      
      <a href="admin_menu/dsc_boletos/index.php" class="link" target="iframe_contenido">
        <i class="bi bi-percent"></i>Descuentos
      </a>
      
      <a href="ctg_boletos/index.php" class="link" target="iframe_contenido">
        <i class="bi bi-easel2-fill"></i>Eventos
      </a>
      
      <a href="admin_menu/rpt_boletos/index.php" class="link" target="iframe_contenido">
        <i class="bi bi-graph-up"></i>Reportes
      </a>

    </nav>
  </aside>

  <main>
    <header>
      <div><strong id="title">Contenido</strong></div>
    </header>

    <iframe 
      src="admin_menu/inicio.php" 
      name="iframe_contenido" 
      class="content-frame"
      title="Contenido principal">
    </iframe>

  </main>

</body>
</html>