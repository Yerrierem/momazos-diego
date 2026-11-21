<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

const CARPETA = 'memes_pool/';
const MAX_BYTES = 5 * 1024 * 1024; // 5 MB por imagen
$tipos_ok = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];

if (!is_dir(CARPETA)) mkdir(CARPETA, 0755, true);

$mensajes = [];
$errores  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ---- Borrar una imagen (solo la que subiste tú)
    if (isset($_POST['borrar'])) {
        $id = (int)$_POST['borrar'];
        $st = $pdo->prepare("SELECT archivo FROM imagenes_meme WHERE id = ? AND subido_por = ?");
        $st->execute([$id, $_SESSION['user_id']]);
        $archivo = $st->fetchColumn();
        if ($archivo) {
            $pdo->prepare("DELETE FROM imagenes_meme WHERE id = ?")->execute([$id]);
            @unlink(CARPETA . basename($archivo));
            $mensajes[] = "Imagen eliminada.";
        }
    }

    // ---- Subir imágenes (puedes elegir varias a la vez)
    if (isset($_FILES['imagenes'])) {
        $f = $_FILES['imagenes'];
        for ($i = 0; $i < count($f['name']); $i++) {
            if ($f['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
            $nombre = $f['name'][$i];
            if ($f['error'][$i] !== UPLOAD_ERR_OK) { $errores[] = "$nombre: error al subir."; continue; }
            if ($f['size'][$i] > MAX_BYTES)        { $errores[] = "$nombre: pesa más de 5 MB."; continue; }

            // Se valida el contenido real del archivo, no solo la extensión
            $info = @getimagesize($f['tmp_name'][$i]);
            if (!$info || !isset($tipos_ok[$info[2]])) { $errores[] = "$nombre: no es una imagen válida (jpg, png, gif o webp)."; continue; }

            $archivo = 'meme_' . bin2hex(random_bytes(8)) . '.' . $tipos_ok[$info[2]];
            if (!move_uploaded_file($f['tmp_name'][$i], CARPETA . $archivo)) { $errores[] = "$nombre: no se pudo guardar."; continue; }

            $pdo->prepare("INSERT INTO imagenes_meme (archivo, subido_por) VALUES (?, ?)")
                ->execute([$archivo, $_SESSION['user_id']]);
            $mensajes[] = "$nombre subida al pool.";
        }
    }
}

$imagenes = $pdo->query("SELECT i.id, i.archivo, i.subido_por, u.username
                         FROM imagenes_meme i LEFT JOIN usuarios u ON u.id = i.subido_por
                         ORDER BY i.id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pool de imágenes</title>
    <style>
        body { font-family: Arial, sans-serif; background: #1a1a2e; color: white; margin: 0; padding: 20px; }
        .caja { background: #16213e; border-radius: 10px; padding: 20px; max-width: 900px; margin: 0 auto 20px; }
        h1, h2 { margin-top: 0; }
        a { color: #ffc107; }
        input[type=file] { background: #0f3460; padding: 10px; border-radius: 6px; width: 100%; box-sizing: border-box; color: white; }
        button { background: #28a745; color: white; border: none; padding: 10px 22px; border-radius: 6px; font-weight: bold; cursor: pointer; margin-top: 12px; font-size: 16px; }
        .ok { color: #7dffa0; } .err { color: #ff8a8a; }
        .galeria { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 12px; }
        .item { background: #0f3460; border-radius: 8px; padding: 8px; text-align: center; font-size: 12px; }
        .item img { width: 100%; height: 120px; object-fit: cover; border-radius: 6px; }
        .item form { margin: 6px 0 0; }
        .item button { background: #dc3545; padding: 4px 10px; font-size: 12px; margin: 0; }
    </style>
</head>
<body>
    <div class="caja">
        <h1>ool de imágenes pa los memerengues</h1>
        <p><a href="index.php">← Volver al inicio</a> · Hay <b><?php echo count($imagenes); ?></b> imágenes (se necesitan mínimo 2 para jugar).</p>

        <?php foreach ($mensajes as $m) echo "<div class='ok'>✔ " . htmlspecialchars($m) . "</div>"; ?>
        <?php foreach ($errores as $e)  echo "<div class='err'>✖ " . htmlspecialchars($e) . "</div>"; ?>

        <form method="POST" enctype="multipart/form-data">
            <input type="file" name="imagenes[]" accept="image/*" multiple required>
            <button type="submit">Subir al pool</button>
        </form>
    </div>

    <div class="caja">
        <h2>Imágenes en el pool</h2>
        <?php if (!$imagenes): ?>
            <p>Todavía no hay imágenes. ¡Sube las primeras!</p>
        <?php endif; ?>
        <div class="galeria">
            <?php foreach ($imagenes as $img): ?>
                <div class="item">
                    <img src="<?php echo CARPETA . htmlspecialchars($img['archivo']); ?>" alt="">
                    <div>por <?php echo htmlspecialchars($img['username'] ?? '—'); ?></div>
                    <?php if ((int)$img['subido_por'] === (int)$_SESSION['user_id']): ?>
                        <form method="POST" onsubmit="return confirm('¿Eliminar esta imagen?');">
                            <input type="hidden" name="borrar" value="<?php echo (int)$img['id']; ?>">
                            <button type="submit">Eliminar</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>
