-- Ejecuta este script UNA sola vez sobre tu base de datos MOMAZOS_DIEGO
USE MOMAZOS_DIEGO;

-- 1) La partida ahora tiene estado y fase (el servidor es quien manda el reloj)
ALTER TABLE partidas
    ADD COLUMN estado ENUM('lobby','jugando','terminada') NOT NULL DEFAULT 'lobby',
    ADD COLUMN fase VARCHAR(20) NOT NULL DEFAULT 'lobby',   -- cuenta | creando | votando | resultados | final
    ADD COLUMN ronda_actual TINYINT NOT NULL DEFAULT 0,
    ADD COLUMN fase_fin INT UNSIGNED NOT NULL DEFAULT 0,    -- unix timestamp en que acaba la fase actual
    ADD COLUMN meme_idx INT NOT NULL DEFAULT 0,             -- meme que se está votando
    ADD COLUMN orden_memes TEXT NULL;                       -- JSON con el orden aleatorio de los memes

-- 2) Pool de imágenes exclusivas para memes
CREATE TABLE imagenes_meme (
    id INT AUTO_INCREMENT PRIMARY KEY,
    archivo VARCHAR(255) NOT NULL UNIQUE,
    subido_por INT NULL,
    fecha_subida TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subido_por) REFERENCES usuarios(id) ON DELETE SET NULL
) DEFAULT CHARSET=utf8mb4;

-- 3) Jugadores que EMPEZARON la partida (foto fija del lobby al pulsar "Iniciar")
CREATE TABLE juego_jugadores (
    partida_id INT NOT NULL,
    usuario_id INT NOT NULL,
    ultimo_ping INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (partida_id, usuario_id),
    FOREIGN KEY (partida_id) REFERENCES partidas(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) DEFAULT CHARSET=utf8mb4;

-- 4) Un meme por jugador por ronda
CREATE TABLE memes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    partida_id INT NOT NULL,
    ronda TINYINT NOT NULL,
    usuario_id INT NOT NULL,
    img_arriba_id INT NULL,
    img_abajo_id INT NULL,
    texto_arriba VARCHAR(255) NOT NULL DEFAULT '',
    texto_abajo VARCHAR(255) NOT NULL DEFAULT '',
    confirmado TINYINT NOT NULL DEFAULT 0,
    puntos_base INT NULL,
    puntos_finales INT NULL,
    UNIQUE KEY uq_meme (partida_id, ronda, usuario_id),
    FOREIGN KEY (partida_id) REFERENCES partidas(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (img_arriba_id) REFERENCES imagenes_meme(id) ON DELETE SET NULL,
    FOREIGN KEY (img_abajo_id) REFERENCES imagenes_meme(id) ON DELETE SET NULL
) DEFAULT CHARSET=utf8mb4;

-- 5) Votos: valor 1 = me gustó, 0 = meh, -1 = no me gustó
CREATE TABLE votos (
    meme_id INT NOT NULL,
    votante_id INT NOT NULL,
    valor TINYINT NOT NULL,
    papu TINYINT NOT NULL DEFAULT 0,
    puntos_papu INT NOT NULL DEFAULT 0,   -- premio/castigo del votante por usar Papu Momo
    PRIMARY KEY (meme_id, votante_id),
    FOREIGN KEY (meme_id) REFERENCES memes(id) ON DELETE CASCADE,
    FOREIGN KEY (votante_id) REFERENCES usuarios(id) ON DELETE CASCADE
) DEFAULT CHARSET=utf8mb4;
