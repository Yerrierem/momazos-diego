<?php
session_start();

// Si no hay sesión, pa' fuera. Lo mandamos al login.
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Inicio - Mi Proyecto</title>
    <style>
        /* Estilos generales */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background-color: #f4f4f9; }

        /* Estilos de la barra de navegación (Navbar) */
        .navbar {
            background-color: #ffffff;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between; /* Separa el logo/usuario de la derecha */
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px; /* Espacio entre la foto y el nombre */
        }

        .user-info img {
            width: 45px;
            height: 45px;
            border-radius: 50%; /* Hace la foto redonda */
            object-fit: cover;
            border: 2px solid #007bff;
        }

        .user-info span {
            font-weight: bold;
            font-size: 18px;
            color: #333;
        }

        .btn-logout {
            background-color: #dc3545;
            color: white;
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            transition: background 0.3s;
        }

        .btn-logout:hover { background-color: #c82333; }

        /* Contenido principal */
        .contenido {
            padding: 40px;
            text-align: center;
        }
    </style>
</head>
<body>

    <!-- Barra de Navegación -->
    <nav class="navbar">
        <div class="user-info">
            <!-- Foto de perfil -->
            <img src="uploads/<?php echo htmlspecialchars($_SESSION['foto_perfil']); ?>" alt="Perfil">
            <!-- Nombre de usuario -->
            <span>Bienvenido, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
        </div>
        
        <!-- Botón de cerrar sesión -->
        <a href="cerrar_sesion.php" class="btn-logout">Cerrar Sesión</a>
    </nav>

    <!-- Área de contenido principal -->
    <main class="contenido">
        <h1>Página de Inicio</h1>
        <p>Aquí puedes poner el contenido de tu aplicación.</p>
    </main>

</body>
</html>