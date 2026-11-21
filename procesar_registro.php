<?php
require 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    // 1. Encriptar la contraseña (¡Nunca guardes texto plano!)
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    // 2. Manejar la foto
    $nombre_foto = "default.png"; // Valor por defecto
    
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['foto']['tmp_name'];
        $fileName = $_FILES['foto']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // Validar que sea una imagen real
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($fileExtension, $allowedExtensions)) {
            // Crear un nombre único para evitar sobreescribir fotos
            $nombre_foto = uniqid('user_') . '.' . $fileExtension;
            
            // Asegurarse de que la carpeta exista
            $uploadDir = 'uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $dest_path = $uploadDir . $nombre_foto;
            move_uploaded_file($fileTmpPath, $dest_path);
        }
    }

    // 3. Insertar en la base de datos
    try {
        $sql = "INSERT INTO usuarios (username, password_hash, foto_perfil) VALUES (:username, :password_hash, :foto_perfil)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':username' => $username,
            ':password_hash' => $password_hash,
            ':foto_perfil' => $nombre_foto
        ]);
        
        echo "<h2>¡Registro exitoso!</h2>";
        echo "<p>Usuario <b>$username</b> creado correctamente.</p>";
        echo "<a href='registro.html'>Volver al registro</a> | <a href='perfil.php?user=$username'>Ver perfil</a>";
        
    } catch (PDOException $e) {
        // Si el username ya existe (por el UNIQUE)
        if ($e->getCode() == 23000) {
            echo "<h2>Error:</h2><p>El nombre de usuario ya está en uso. <a href='registro.html'>Intenta con otro</a>.</p>";
        } else {
            echo "Error: " . $e->getMessage();
        }
    }
}
?>