<?php
$servername = "localhost";
$username = "root";       // usuario de MySQL
$password = "";           // contraseña de MySQL (vacía por defecto en XAMPP)
$database = "trt_25";     // nombre de la base de datos

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}
?>
