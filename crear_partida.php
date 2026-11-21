<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

// Generar un código de sala único (ej: ABCD12)
$codigo = strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));

try {
    $sql = "INSERT INTO partidas (codigo_sala, creador_id) VALUES (:codigo, :creador)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':codigo' => $codigo, ':creador' => $_SESSION['user_id']]);
    
    // Redirigir al lobby con el código de la sala
    header("Location: lobby.php?sala=" . $codigo);
    exit();
} catch (PDOException $e) {
    echo "Error al crear la partida: " . $e->getMessage();
}
?>