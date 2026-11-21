<?php
session_start();
// Si hay sesión, guardamos los datos, si no, quedan vacíos
$logueado = isset($_SESSION['user_id']);
$username = $logueado ? $_SESSION['username'] : '';
$foto = $logueado ? $_SESSION['foto_perfil'] : '';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Momazos Diego (con nombre temporal)</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f9; margin: 0; }
        
        /* Barra superior solo si está logueado */
        .navbar { background: #ffffff; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .user-info { display: flex; align-items: center; gap: 15px; }
        .user-info img { width: 45px; height: 45px; border-radius: 50%; object-fit: cover; border: 2px solid #007bff; }
        .btn-logout { background-color: #dc3545; color: white; padding: 8px 16px; text-decoration: none; border-radius: 5px; font-weight: bold; }

        /* Centro de la página */
        .hero { text-align: center; padding: 80px 20px; }
        h1 { color: #333; }
        p { color: #666; margin-bottom: 30px; }
        
        .btn { display: inline-block; padding: 12px 24px; margin: 10px; text-decoration: none; border-radius: 5px; font-weight: bold; color: white; font-size: 16px; }
        .btn-login { background-color: #007bff; }
        .btn-register { background-color: #28a745; }
        .btn-game { background-color: #ff9800; font-size: 20px; padding: 15px 40px; }
        
        /* Sección de juego */
        .seccion-juego { margin-top: 40px; padding: 20px; background: #fff; display: inline-block; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
    </style>
</head>
<body>

    <!-- BARRA SUPERIOR (Solo aparece si está logueado) -->
    <?php if ($logueado): ?>
    <nav class="navbar">
        <div class="user-info">
            <img src="uploads/<?php echo htmlspecialchars($foto); ?>" alt="Perfil">
            <span>Bienvenido, papu <b><?php echo htmlspecialchars($username); ?></b></span>
        </div>
        <a href="cerrar_sesion.php" class="btn-logout">Cerrar Sesión</a>
    </nav>
    <?php endif; ?>

    <!-- CONTENIDO PRINCIPAL -->
    <div class="hero">
        <h1>Momazos Diego (con nombre temporal)</h1>
        <p>Insertar texto :v</p>

        <!-- SI NO ESTÁ LOGUEADO: Ve los botones de Login y Registro -->
        <?php if (!$logueado): ?>
            <a href="login.html" class="btn btn-login">Iniciar Sesión</a>
            <a href="registro.html" class="btn btn-register">Registrarse</a>
        
        <!-- SI YA ESTÁ LOGUEADO: Ve los botones de Juego -->
        <?php else: ?>
            <div class="seccion-juego">
                <h2>¿Listo para jugar?</h2>
                <!-- Este botón va a crear_partida.php -->
                <a href="crear_partida.php" class="btn btn-game">➕ Crear Partida</a>
                
                <!-- Aquí podrías agregar un formulario para unirse a una sala existente -->
                <form action="lobby.php" method="GET" style="margin-top: 20px;">
                    <input type="text" name="sala" placeholder="Código de la sala" required style="padding: 10px; border-radius: 5px; border: 1px solid #ccc;">
                    <button type="submit" class="btn btn-game" style="padding: 11px 20px; font-size: 16px;">Unirse</button>
                </form>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>