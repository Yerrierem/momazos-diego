<?php
session_start();
require 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $sql = "SELECT id, username, password_hash, foto_perfil FROM usuarios WHERE username = :username";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':username' => $username]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario && password_verify($password, $usuario['password_hash'])) {
        $_SESSION['user_id'] = $usuario['id'];
        $_SESSION['username'] = $usuario['username'];
        $_SESSION['foto_perfil'] = $usuario['foto_perfil'];

        // ¡EL CAMBIO ESTÁ AQUÍ! Redirigimos al index principal
        header("Location: index.php");
        exit();
    } else {
        echo "<h2>Error de inicio de sesión</h2>";
        echo "<p>El nombre de usuario o la contraseña son incorrectos.</p>";
        echo "<a href='login.html'>Volver a intentar</a>";
    }
}
?>