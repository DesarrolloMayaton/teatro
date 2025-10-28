<?php
$conn = new mysqli("localhost", "root", "", "trt_25");
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}
echo "Conexión exitosa!";
?>
