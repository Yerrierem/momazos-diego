<?php
require 'db.php';

$user = isset($_GET['user']) ? $_GET['user'] : '';

if ($user) {
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE username = :user");
    $stmt->execute([':user' => $user]);
    $datos = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($datos) {
        echo "<h1>Perfil de " . htmlspecialchars($datos['username']) . "</h1>";
        echo "<img src='uploads/" . htmlspecialchars($datos['foto_perfil']) . "' alt='Foto de perfil' width='150' style='border-radius: 50%;'><br>";
        echo "<p>ID de usuario: " . $datos['id'] . "</p>";
    } else {
        echo "<p>Usuario no encontrado.</p>";
    }
}
?>