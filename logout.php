<?php
session_start();

require_once 'transacciones_helper.php';
registrar_transaccion('logout', 'Cierre de sesión');

// Destruir todas las variables de sesión
$_SESSION = array();

// Destruir la sesión
session_destroy();

// Redirigir al login
header("Location: login.php");
exit();
?>
