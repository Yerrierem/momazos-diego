<?php
session_start();
require_once __DIR__ . '/juego_lib.php';

if (!isset($_SESSION['user_id'])) {
    json_out(['ok' => false, 'error' => 'Sesión expirada', 'redirigir' => 'login.html'], 401);
}
$uid    = (int)$_SESSION['user_id'];
$accion = $_REQUEST['accion'] ?? '';
$codigo = $_REQUEST['sala'] ?? '';

$p = partida_por_codigo($pdo, $codigo);
if (!$p) json_out(['ok' => false, 'error' => 'La sala no existe', 'redirigir' => 'index.php']);
$pid = (int)$p['id'];

// ---------------------------------------------------------------- CONFIGURACIÓN (desde el lobby)
if ($accion === 'config') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'error' => 'Método inválido'], 405);
    if ((int)$p['creador_id'] !== $uid) json_out(['ok' => false, 'error' => 'Solo el anfitrión puede cambiar la configuración.']);
    if ($p['estado'] !== 'lobby') json_out(['ok' => false, 'error' => 'La partida ya empezó, no se puede cambiar.']);

    // Solo se aceptan valores de la lista permitida; lo demás se ignora
    $nuevo = [];
    foreach (OPCIONES_CONFIG as $campo => $permitidos) {
        if (!isset($_POST[$campo])) continue;
        $v = (int)$_POST[$campo];
        if (!in_array($v, $permitidos, true)) json_out(['ok' => false, 'error' => "Valor no permitido para $campo"]);
        $nuevo[$campo] = $v;
    }
    foreach ($nuevo as $campo => $v) {   // $campo viene de nuestra lista fija, no del usuario
        $pdo->prepare("UPDATE partidas SET $campo = ? WHERE id = ? AND estado = 'lobby'")->execute([$v, $pid]);
    }
    $p = partida_por_codigo($pdo, $codigo);
    json_out(['ok' => true, 'config' => config_de($p)]);
}

// ---------------------------------------------------------------- INICIAR (desde el lobby)
if ($accion === 'iniciar') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'error' => 'Método inválido'], 405);
    if ((int)$p['creador_id'] !== $uid) json_out(['ok' => false, 'error' => 'Solo quien creó la sala puede iniciar.']);

    $pdo->prepare("DELETE FROM partida_jugadores WHERE partida_id = ? AND ultima_actividad < NOW() - INTERVAL 10 SECOND")
        ->execute([$pid]);

    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare("SELECT estado FROM partidas WHERE id = ? FOR UPDATE");
        $st->execute([$pid]);
        if ($st->fetchColumn() !== 'lobby') {
            $pdo->rollBack();
            json_out(['ok' => true]); // ya estaba iniciada
        }
        $st = $pdo->prepare("SELECT usuario_id FROM partida_jugadores WHERE partida_id = ?");
        $st->execute([$pid]);
        $jugadores = $st->fetchAll(PDO::FETCH_COLUMN);

        if (count($jugadores) < MIN_JUGADORES) {
            $pdo->rollBack();
            json_out(['ok' => false, 'error' => 'Se necesitan al menos ' . MIN_JUGADORES . ' jugadores para iniciar.']);
        }
        $n_img = (int)$pdo->query("SELECT COUNT(*) FROM imagenes_meme")->fetchColumn();
        if ($n_img < MIN_IMAGENES) {
            $pdo->rollBack();
            json_out(['ok' => false, 'error' => 'El pool de imágenes tiene ' . $n_img . '. Sube al menos ' . MIN_IMAGENES . ' en "Subir imágenes".']);
        }

        $ins = $pdo->prepare("INSERT IGNORE INTO juego_jugadores (partida_id, usuario_id, ultimo_ping) VALUES (?, ?, ?)");
        foreach ($jugadores as $j) $ins->execute([$pid, (int)$j, time()]);

        $pdo->prepare("UPDATE partidas SET estado='jugando', fase='cuenta', ronda_actual=1, fase_fin=?, meme_idx=0, orden_memes=NULL WHERE id=?")
            ->execute([time() + SEGUNDOS_CUENTA, $pid]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        json_out(['ok' => false, 'error' => 'No se pudo iniciar: ' . $e->getMessage()], 500);
    }
    json_out(['ok' => true]);
}

// ---------------------------------------------------------------- A partir de aquí: partida en curso
if ($p['estado'] === 'lobby') json_out(['ok' => false, 'redirigir' => 'lobby.php?sala=' . urlencode($codigo)]);
if (!es_participante($pdo, $pid, $uid)) {
    json_out(['ok' => false, 'error' => 'No participas en esta partida', 'redirigir' => 'index.php']);
}

// "Sigo aquí"
$pdo->prepare("UPDATE juego_jugadores SET ultimo_ping = ? WHERE partida_id = ? AND usuario_id = ?")
    ->execute([time(), $pid, $uid]);

function recargar(PDO $pdo, int $pid) {
    $st = $pdo->prepare("SELECT * FROM partidas WHERE id = ?");
    $st->execute([$pid]);
    return $st->fetch();
}

function fila_meme(PDO $pdo, string $where, array $params) {
    $st = $pdo->prepare("SELECT m.*, a.archivo AS arch_a, b.archivo AS arch_b
                         FROM memes m
                         LEFT JOIN imagenes_meme a ON a.id = m.img_arriba_id
                         LEFT JOIN imagenes_meme b ON b.id = m.img_abajo_id
                         WHERE $where");
    $st->execute($params);
    return $st->fetch();
}

function fmt_meme(array $m): array {
    return ['id' => (int)$m['id'],
            'arriba' => url_imagen($m['arch_a']), 'abajo' => url_imagen($m['arch_b']),
            'ta' => $m['texto_arriba'], 'tb' => $m['texto_abajo']];
}

/** La configuración de la partida en un arreglo listo para mandar como JSON */
function config_de(array $p): array {
    return ['total_rondas' => (int)$p['total_rondas'], 'seg_crear' => (int)$p['seg_crear'],
            'max_rerolls' => (int)$p['max_rerolls'], 'modo_memes' => (int)$p['modo_memes']];
}

function limpiar_texto($t): string {
    $t = str_replace(["\r\n", "\r", "\n"], ' ', (string)$t);
    // Recorta a MAX_TEXTO caracteres (UTF-8) sin depender de la extensión mbstring
    return preg_match('/^.{0,' . MAX_TEXTO . '}/us', $t, $m) ? $m[0] : '';
}

// Ventana en la que aún se aceptan cambios del meme
function creacion_abierta(array $p): bool {
    return $p['estado'] === 'jugando' && $p['fase'] === 'creando' && time() <= (int)$p['fase_fin'] + GRACIA_CREAR;
}

switch ($accion) {

// ---------------------------------------------------------------- ESTADO
case 'estado':
    avanzar_partida($pdo, $pid);
    $p = recargar($pdo, $pid);
    $ronda = (int)$p['ronda_actual'];
    $n = contar_participantes($pdo, $pid);

    $out = ['ok' => true, 'ahora' => time(), 'estado' => $p['estado'], 'fase' => $p['fase'],
            'ronda' => $ronda, 'total_rondas' => (int)$p['total_rondas'], 'fin' => (int)$p['fase_fin'],
            'modo' => (int)$p['modo_memes'], 'seg_crear' => (int)$p['seg_crear'], 'max_rerolls' => (int)$p['max_rerolls'],
            'yo' => $uid, 'n' => $n, 'meme_idx' => (int)$p['meme_idx']];

    if ($p['fase'] === 'creando') {
        $m = fila_meme($pdo, "m.partida_id = ? AND m.ronda = ? AND m.usuario_id = ?", [$pid, $ronda, $uid]);
        $st = $pdo->prepare("SELECT COALESCE(SUM(confirmado),0) FROM memes WHERE partida_id = ? AND ronda = ?");
        $st->execute([$pid, $ronda]);
        $out['meme'] = $m ? fmt_meme($m) : null;
        $out['rerolls_usados'] = $m ? (int)$m['rerolls_usados'] : 0;
        $out['confirmado'] = $m ? (bool)$m['confirmado'] : false;
        $out['listos'] = (int)$st->fetchColumn();

    } elseif ($p['fase'] === 'votando') {
        $orden = json_decode($p['orden_memes'] ?? '[]', true) ?: [];
        $idx = (int)$p['meme_idx'];
        $meme_id = (int)($orden[$idx] ?? 0);
        $m = $meme_id ? fila_meme($pdo, "m.id = ?", [$meme_id]) : null;
        if ($m) {
            $st = $pdo->prepare("SELECT valor, papu FROM votos WHERE meme_id = ? AND votante_id = ?");
            $st->execute([$meme_id, $uid]);
            $mi = $st->fetch();
            $st = $pdo->prepare("SELECT COUNT(*) FROM votos WHERE meme_id = ?");
            $st->execute([$meme_id]);
            $out['voto'] = ['meme' => fmt_meme($m), 'idx' => $idx, 'total' => count($orden),
                            'es_mio' => ((int)$m['usuario_id'] === $uid),
                            'mi_voto' => $mi ? ['valor' => (int)$mi['valor'], 'papu' => (int)$mi['papu']] : null,
                            'votaron' => (int)$st->fetchColumn(), 'votantes' => max(0, $n - 1)];
        }

    } elseif ($p['fase'] === 'resultados') {
        $st = $pdo->prepare("SELECT m.id, m.usuario_id, m.texto_arriba, m.texto_abajo, m.puntos_base, m.puntos_finales,
                                    a.archivo AS arch_a, b.archivo AS arch_b, u.username, u.foto_perfil,
                                    COALESCE(SUM(v.valor = 1),0) AS likes, COALESCE(SUM(v.valor = 0),0) AS mehs,
                                    COALESCE(SUM(v.valor = -1),0) AS dislikes, COALESCE(SUM(v.papu),0) AS papus
                             FROM memes m
                             JOIN usuarios u ON u.id = m.usuario_id
                             LEFT JOIN imagenes_meme a ON a.id = m.img_arriba_id
                             LEFT JOIN imagenes_meme b ON b.id = m.img_abajo_id
                             LEFT JOIN votos v ON v.meme_id = m.id
                             WHERE m.partida_id = ? AND m.ronda = ?
                             GROUP BY m.id
                             ORDER BY m.puntos_finales DESC, m.id ASC");
        $st->execute([$pid, $ronda]);
        $memes = [];
        foreach ($st->fetchAll() as $r) {
            $memes[] = ['id' => (int)$r['id'], 'autor' => $r['username'], 'foto' => $r['foto_perfil'],
                        'arriba' => url_imagen($r['arch_a']), 'abajo' => url_imagen($r['arch_b']),
                        'ta' => $r['texto_arriba'], 'tb' => $r['texto_abajo'],
                        'likes' => (int)$r['likes'], 'mehs' => (int)$r['mehs'], 'dislikes' => (int)$r['dislikes'],
                        'papus' => (int)$r['papus'], 'base' => (int)$r['puntos_base'], 'final' => (int)$r['puntos_finales']];
        }
        $out['memes'] = $memes;
        $out['puntajes'] = array_values(calcular_puntajes($pdo, $pid, (int)$p['total_rondas']));

    } elseif ($p['fase'] === 'final') {
        $out['puntajes'] = array_values(calcular_puntajes($pdo, $pid, (int)$p['total_rondas']));
    }
    json_out($out);

// ---------------------------------------------------------------- GUARDAR / CONFIRMAR
case 'guardar':
    if (!creacion_abierta($p)) json_out(['ok' => false, 'error' => 'El tiempo para editar terminó']);
    $ronda = (int)$p['ronda_actual'];
    $ta = limpiar_texto($_POST['ta'] ?? '');
    $tb = ((int)$p['modo_memes'] === 1) ? '' : limpiar_texto($_POST['tb'] ?? '');
    $conf = !empty($_POST['confirmar']) ? 1 : 0;
    // Solo se modifica si aún no está confirmado
    $pdo->prepare("UPDATE memes SET texto_arriba = ?, texto_abajo = ?, confirmado = IF(confirmado = 1, 1, ?)
                   WHERE partida_id = ? AND ronda = ? AND usuario_id = ? AND confirmado = 0")
        ->execute([$ta, $tb, $conf, $pid, $ronda, $uid]);
    if ($conf) avanzar_partida($pdo, $pid);
    json_out(['ok' => true]);

// ---------------------------------------------------------------- REROLL
case 'reroll':
    if (!creacion_abierta($p)) json_out(['ok' => false, 'error' => 'El tiempo para editar terminó']);
    $ronda = (int)$p['ronda_actual'];
    $modo  = (int)$p['modo_memes'];
    $cual = ($_POST['cual'] ?? '') === 'abajo' ? 'abajo' : 'arriba';
    if ($modo === 1) $cual = 'arriba';   // en modo de un solo momo solo existe la imagen de arriba
    $m = fila_meme($pdo, "m.partida_id = ? AND m.ronda = ? AND m.usuario_id = ?", [$pid, $ronda, $uid]);
    if (!$m || $m['confirmado']) json_out(['ok' => false, 'error' => 'Tu momo ya está confirmado']);

    $max = (int)$p['max_rerolls'];
    if ((int)$m['rerolls_usados'] >= $max) json_out(['ok' => false, 'error' => 'Ya gastaste todos tus rerolls', 'restantes' => 0]);

    $actual = (int)($cual === 'arriba' ? $m['img_arriba_id'] : $m['img_abajo_id']);
    $otra   = $modo === 1 ? 0 : (int)($cual === 'arriba' ? $m['img_abajo_id'] : $m['img_arriba_id']);

    // Preferimos una imagen distinta a la actual y a la otra; si el pool es chico, relajamos
    $nueva = null;
    foreach ([[$actual, $otra], [$actual, $actual]] as [$x, $y]) {
        $st = $pdo->prepare("SELECT id FROM imagenes_meme WHERE id <> ? AND id <> ? ORDER BY RAND() LIMIT 1");
        $st->execute([$x, $y]);
        $nueva = $st->fetchColumn();
        if ($nueva) break;
    }
    if (!$nueva) json_out(['ok' => false, 'error' => 'No hay más imágenes en el pool']);

    // Se cobra el reroll y se cambia la imagen en una sola sentencia (así dos clics seguidos no se cuelan)
    $col = $cual === 'arriba' ? 'img_arriba_id' : 'img_abajo_id';
    $st = $pdo->prepare("UPDATE memes SET $col = ?, rerolls_usados = rerolls_usados + 1
                         WHERE id = ? AND confirmado = 0 AND rerolls_usados < ?");
    $st->execute([(int)$nueva, (int)$m['id'], $max]);
    if ($st->rowCount() === 0) json_out(['ok' => false, 'error' => 'Ya gastaste todos tus rerolls', 'restantes' => 0]);

    $st = $pdo->prepare("SELECT archivo FROM imagenes_meme WHERE id = ?");
    $st->execute([(int)$nueva]);
    json_out(['ok' => true, 'cual' => $cual, 'url' => url_imagen($st->fetchColumn()),
              'restantes' => $max - (int)$m['rerolls_usados'] - 1]);

// ---------------------------------------------------------------- VOTAR
case 'votar':
    if ($p['fase'] !== 'votando' || time() > (int)$p['fase_fin'] + 1) json_out(['ok' => false, 'error' => 'Votación cerrada']);
    $orden = json_decode($p['orden_memes'] ?? '[]', true) ?: [];
    $meme_id = (int)($orden[(int)$p['meme_idx']] ?? 0);
    if ($meme_id !== (int)($_POST['meme_id'] ?? 0)) json_out(['ok' => false, 'error' => 'Ese meme ya no se está votando']);

    $st = $pdo->prepare("SELECT usuario_id FROM memes WHERE id = ?");
    $st->execute([$meme_id]);
    if ((int)$st->fetchColumn() === $uid) json_out(['ok' => false, 'error' => 'No puedes votar tu propio meme']);

    $valor = (int)($_POST['valor'] ?? 0);
    if (!in_array($valor, [-1, 0, 1], true)) json_out(['ok' => false, 'error' => 'Voto inválido']);
    $papu = !empty($_POST['papu']) ? 1 : 0;

    $pdo->prepare("INSERT INTO votos (meme_id, votante_id, valor, papu) VALUES (?, ?, ?, ?)
                   ON DUPLICATE KEY UPDATE valor = VALUES(valor), papu = VALUES(papu)")
        ->execute([$meme_id, $uid, $valor, $papu]);
    avanzar_partida($pdo, $pid);
    json_out(['ok' => true]);

default:
    json_out(['ok' => false, 'error' => 'Acción desconocida'], 400);
}
