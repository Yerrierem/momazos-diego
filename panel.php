<?php
session_start();

// Si no hay sesión iniciada, redirigir al login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mi Panel</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding-top: 50px; background-color: #f4f4f9;}
        .card { background: white; display: inline-block; padding: 30px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        img { border-radius: 50%; width: 100px; height: 100px; object-fit: cover; border: 3px solid #28a745; }
        .btn { display: inline-block; margin-top: 20px; padding: 10px 20px; background: #dc3545; color: white; text-decoration: none; border-radius: 4px; }
    </style>
</head>
<body>

<div class="card">
    <!-- Mostramos la foto del usuario -->
    <img src="uploads/<?php echo htmlspecialchars($_SESSION['foto_perfil']); ?>" alt="Foto de perfil">
    
    <h1>¡Hola, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
    <p>Has iniciado sesión correctamente.</p>
    
    <a href="cerrar_sesion.php" class="btn">Cerrar Sesión</a>
</div>

</body>
</html>