<?php
session_start();
require 'db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['jugadores' => [], 'estado' => 'lobby', 'es_creador' => false, 'config' => null]);
    exit();
}

$partida_id = isset($_GET['partida_id']) ? (int)$_GET['partida_id'] : 0;

if ($partida_id > 0) {
    // 1. Limpiamos a los fantasmas (los que cerraron la pestaña y no avisaron)
    $stmt_limpieza = $pdo->prepare("DELETE FROM partida_jugadores WHERE partida_id = :partida_id AND ultima_actividad < NOW() - INTERVAL 10 SECOND");
    $stmt_limpieza->execute([':partida_id' => $partida_id]);

    // 2. Traemos a los jugadores reales
    $sql = "SELECT u.username, u.foto_perfil
            FROM partida_jugadores pj
            JOIN usuarios u ON pj.usuario_id = u.id
            WHERE pj.partida_id = :partida_id
            ORDER BY pj.fecha_union ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':partida_id' => $partida_id]);
    $jugadores = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Estado de la partida (para saber si ya empezó) y si quien consulta es el anfitrión
    $stmt = $pdo->prepare("SELECT estado, creador_id, total_rondas, seg_crear, max_rerolls, modo_memes FROM partidas WHERE id = :id");
    $stmt->execute([':id' => $partida_id]);
    $partida = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'jugadores'  => $jugadores,
        'estado'     => $partida ? $partida['estado'] : 'lobby',
        'es_creador' => $partida ? ((int)$partida['creador_id'] === (int)$_SESSION['user_id']) : false,
        'config'     => $partida ? [
            'total_rondas' => (int)$partida['total_rondas'], 'seg_crear' => (int)$partida['seg_crear'],
            'max_rerolls'  => (int)$partida['max_rerolls'],  'modo_memes' => (int)$partida['modo_memes']
        ] : null
    ]);
} else {
    echo json_encode(['jugadores' => [], 'estado' => 'lobby', 'es_creador' => false, 'config' => null]);
}
?>
