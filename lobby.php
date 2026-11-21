<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

$codigo_sala = isset($_GET['sala']) ? $_GET['sala'] : '';
if (empty($codigo_sala)) {
    header("Location: index.php");
    exit();
}

// Buscar la partida
$stmt = $pdo->prepare("SELECT id, estado, creador_id, total_rondas, seg_crear, max_rerolls, modo_memes FROM partidas WHERE codigo_sala = :codigo");
$stmt->execute([':codigo' => $codigo_sala]);
$partida = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$partida) {
    die("La sala no existe o fue cerrada.");
}

$partida_id = $partida['id'];
$es_creador = ((int)$partida['creador_id'] === (int)$_SESSION['user_id']);

// Si la partida ya empezó: los que juegan vuelven a ella, los demás no pueden entrar
if ($partida['estado'] !== 'lobby') {
    $stmt = $pdo->prepare("SELECT 1 FROM juego_jugadores WHERE partida_id = :p AND usuario_id = :u");
    $stmt->execute([':p' => $partida_id, ':u' => $_SESSION['user_id']]);
    if ($stmt->fetchColumn()) {
        header("Location: juego.php?sala=" . urlencode($codigo_sala));
        exit();
    }
    die("Esta partida ya comenzó y no admite más jugadores. <a href='index.php'>Volver</a>");
}

// Limpiar jugadores inactivos (los que no han dado señales en más de 10 segundos)
$stmt_limpieza = $pdo->prepare("DELETE FROM partida_jugadores WHERE partida_id = :partida_id AND ultima_actividad < NOW() - INTERVAL 10 SECOND");
$stmt_limpieza->execute([':partida_id' => $partida_id]);

// Unir al usuario a la sala (o actualizar su actividad si ya está)
$stmt_join = $pdo->prepare("INSERT INTO partida_jugadores (partida_id, usuario_id, ultima_actividad)
                            VALUES (:partida_id, :usuario_id, NOW())
                            ON DUPLICATE KEY UPDATE ultima_actividad = NOW()");
$stmt_join->execute([':partida_id' => $partida_id, ':usuario_id' => $_SESSION['user_id']]);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Lobby - Sala <?php echo htmlspecialchars($codigo_sala); ?></title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #1a1a2e; color: white; margin: 0; padding: 20px; }
        .header { display: flex; justify-content: space-between; align-items: center; background: #16213e; padding: 15px 30px; border-radius: 8px; margin-bottom: 20px;}
        .sala-codigo { font-size: 24px; font-weight: bold; color: #e94560; letter-spacing: 2px; }
        .grid-jugadores { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 20px; }
        .jugador-card { background: #0f3460; padding: 20px; border-radius: 10px; text-align: center; box-shadow: 0 4px 10px rgba(0,0,0,0.3); transition: transform 0.2s; }
        .jugador-card:hover { transform: translateY(-5px); }
        .jugador-card img { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid #e94560; margin-bottom: 10px; }
        .btn-salir { background-color: #e94560; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold; }
        .btn-salir:hover { background-color: #b83048; }

        .acciones { display: flex; align-items: center; gap: 15px; flex-wrap: wrap; margin: 0 0 25px; }
        .btn-iniciar { background: #28a745; color: white; border: none; padding: 14px 32px; font-size: 18px; font-weight: bold; border-radius: 8px; cursor: pointer; }
        .btn-iniciar:hover { background: #218838; }
        .btn-iniciar:disabled { background: #555; cursor: wait; }
        .btn-link { color: #ffc107; font-weight: bold; text-decoration: none; }
        .msg-error { color: #ff8a8a; font-weight: bold; }
        .msg-espera { color: #aab; }

        /* Configuración de la partida */
        .config { background: #16213e; border-radius: 10px; padding: 18px 24px; margin: 0 0 25px; }
        .config h3 { margin: 0 0 4px; }
        .config .nota { color: #aab; font-size: 13px; margin: 0 0 12px; }
        .cfg-fila { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; padding: 8px 0; border-top: 1px solid #243056; }
        .cfg-nom { min-width: 170px; font-weight: bold; }
        .cfg-nom small { display: block; font-weight: normal; color: #9aa5c8; font-size: 12px; }
        .chips { display: flex; gap: 8px; flex-wrap: wrap; }
        .chip { background: #0f3460; color: #fff; border: 2px solid transparent; padding: 8px 16px; border-radius: 20px; font-size: 15px; font-weight: bold; cursor: pointer; }
        .chip:hover:not(:disabled) { filter: brightness(1.3); }
        .chip.activo { background: #e94560; border-color: #fff; }
        .chip:disabled { cursor: default; opacity: .75; }
        .chip.activo:disabled { opacity: 1; }
    </style>
</head>
<body>

    <div class="header">
        <div>
            <span>Lobby de espera</span>
            <div class="sala-codigo">Sala: <?php echo htmlspecialchars($codigo_sala); ?></div>
        </div>
        <a href="salir_sala.php?partida_id=<?php echo $partida_id; ?>" class="btn-salir">Salir de la Sala</a>
    </div>

    <div class="acciones">
        <?php if ($es_creador): ?>
            <button id="btn-iniciar" class="btn-iniciar">▶ Iniciar partida</button>
            <span class="msg-espera">Mínimo 2 jugadores</span>
        <?php else: ?>
            <span class="msg-espera">⏳ Esperando a que el anfitrión inicie la partida…</span>
        <?php endif; ?>
        <span id="msg-error" class="msg-error"></span>
    </div>

    <div class="config">
        <h3>Configuración</h3>
        <p class="nota" id="cfg-nota"><?php echo $es_creador ? 'Tú mandas, papu: lo que elijas aquí le cambia a todos.' : 'Solo el anfitrión puede cambiar esto.'; ?></p>

        <div class="cfg-fila">
            <div class="cfg-nom">Plantillas<small>cantidad de imágenes por plantilla</small></div>
            <div class="chips" data-campo="modo_memes"></div>
        </div>
        <div class="cfg-fila">
            <div class="cfg-nom">Rondas<small>cuántas rondas se juegan</small></div>
            <div class="chips" data-campo="total_rondas"></div>
        </div>
        <div class="cfg-fila">
            <div class="cfg-nom">Tiempo para hornear<small>segundos por ronda</small></div>
            <div class="chips" data-campo="seg_crear"></div>
        </div>
        <div class="cfg-fila">
            <div class="cfg-nom">Rerolls<small>cambios de imagen por ronda</small></div>
            <div class="chips" data-campo="max_rerolls"></div>
        </div>
    </div>

    <h3>Jugadores conectados (<span id="contador">0</span>/10)</h3>
    <div class="grid-jugadores" id="contenedor-jugadores"></div>

    <script>
        const partidaId = <?php echo (int)$partida_id; ?>;
        const codigoSala = <?php echo json_encode($codigo_sala); ?>;
        let yaRedirigido = false;

        // ---------- Configuración ----------
        const ESCREADOR = <?php echo $es_creador ? 'true' : 'false'; ?>;
        let config = <?php echo json_encode([
            'modo_memes'   => (int)$partida['modo_memes'],
            'total_rondas' => (int)$partida['total_rondas'],
            'seg_crear'    => (int)$partida['seg_crear'],
            'max_rerolls'  => (int)$partida['max_rerolls'],
        ]); ?>;
        // [valor, texto del botón]
        const OPCIONES = {
            modo_memes:   [[2, 'Dos momos'], [1, 'Un solo momo']],
            total_rondas: [[1, '1'], [3, '3'], [5, '5'], [7, '7'], [9, '9']],
            seg_crear:    [60, 90, 120, 150, 180, 210, 240, 270].map(s => [s, s + ' s']),
            max_rerolls:  [[5, '5'], [10, '10'], [12, '12'], [15, '15'], [20, '20']],
        };
        let guardandoConfig = false;

        function pintarConfig() {
            document.querySelectorAll('.chips').forEach(caja => {
                const campo = caja.dataset.campo;
                if (!caja.children.length) {
                    OPCIONES[campo].forEach(([valor, texto]) => {
                        const b = document.createElement('button');
                        b.type = 'button';
                        b.className = 'chip';
                        b.textContent = texto;
                        b.dataset.valor = valor;
                        b.disabled = !ESCREADOR;
                        b.addEventListener('click', () => cambiarConfig(campo, valor));
                        caja.appendChild(b);
                    });
                }
                [...caja.children].forEach(b => b.classList.toggle('activo', Number(b.dataset.valor) === Number(config[campo])));
            });
        }

        async function cambiarConfig(campo, valor) {
            if (!ESCREADOR) return;
            const anterior = config[campo];
            config[campo] = valor;           // se ve al instante
            pintarConfig();
            guardandoConfig = true;
            const msg = document.getElementById('msg-error');
            try {
                const r = await fetch('juego_api.php', {
                    method: 'POST',
                    body: new URLSearchParams({ accion: 'config', sala: codigoSala, [campo]: valor })
                });
                const d = await r.json();
                if (!d.ok) { config[campo] = anterior; pintarConfig(); msg.textContent = d.error || 'No se pudo guardar.'; }
                else { msg.textContent = ''; config = d.config; pintarConfig(); }
            } catch (e) {
                config[campo] = anterior; pintarConfig();
                msg.textContent = 'Error de conexión.';
            } finally { guardandoConfig = false; }
        }
        pintarConfig();

        function irAlJuego() {
            if (yaRedirigido) return;
            yaRedirigido = true;
            window.location.href = 'juego.php?sala=' + encodeURIComponent(codigoSala);
        }

        async function actualizarJugadores() {
            try {
                const respuesta = await fetch(`obtener_jugadores.php?partida_id=${partidaId}`);
                const data = await respuesta.json();

                // Si el anfitrión ya inició, todos pasan al juego
                if (data.estado && data.estado !== 'lobby') { irAlJuego(); return; }

                // Los demás jugadores ven en vivo lo que el anfitrión va cambiando
                if (!ESCREADOR && data.config) { config = data.config; pintarConfig(); }

                const jugadores = data.jugadores;
                document.getElementById('contador').innerText = jugadores.length;
                const contenedor = document.getElementById('contenedor-jugadores');
                contenedor.innerHTML = '';

                jugadores.forEach(jugador => {
                    const card = document.createElement('div');
                    card.className = 'jugador-card';
                    const img = document.createElement('img');
                    img.src = 'uploads/' + jugador.foto_perfil;
                    img.alt = 'Foto';
                    const nombre = document.createElement('h4');
                    nombre.textContent = jugador.username;
                    card.appendChild(img);
                    card.appendChild(nombre);
                    contenedor.appendChild(card);
                });
            } catch (error) {
                console.error("Error:", error);
            }
        }

        // Heartbeat: avisamos que seguimos aquí
        async function avisarActividad() {
            await fetch(`actualizar_actividad.php?partida_id=${partidaId}`);
        }

        <?php if ($es_creador): ?>
        document.getElementById('btn-iniciar').addEventListener('click', async (e) => {
            const btn = e.currentTarget;
            const msg = document.getElementById('msg-error');
            msg.textContent = '';
            btn.disabled = true;
            try {
                const r = await fetch('juego_api.php', {
                    method: 'POST',
                    body: new URLSearchParams({ accion: 'iniciar', sala: codigoSala })
                });
                const d = await r.json();
                if (d.ok) { irAlJuego(); }
                else { msg.textContent = d.error || 'No se pudo iniciar.'; btn.disabled = false; }
            } catch (err) {
                msg.textContent = 'Error de conexión.';
                btn.disabled = false;
            }
        });
        <?php endif; ?>

        actualizarJugadores();
        avisarActividad();
        setInterval(actualizarJugadores, 2000);
        setInterval(avisarActividad, 5000);
    </script>

</body>
</html>
