<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    exit();
}

$partida_id = isset($_GET['partida_id']) ? (int)$_GET['partida_id'] : 0;

if ($partida_id > 0) {
    $sql = "UPDATE partida_jugadores SET ultima_actividad = NOW() 
            WHERE partida_id = :partida_id AND usuario_id = :usuario_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':partida_id' => $partida_id,
        ':usuario_id' => $_SESSION['user_id']
    ]);
}
?>