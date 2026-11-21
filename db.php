<?php
$host = "localhost";
$port = "3307";
$user = "root";
$password = "rJ2XXP7U";
$dbname = "MOMAZOS_DIEGO";

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>