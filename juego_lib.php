<?php
/**
 * Motor del juego "Momazos".
 * El servidor es quien manda: guarda en la BD la fase actual y la hora en que termina.
 * Cada vez que un cliente pregunta el estado (juego_api.php?accion=estado) se revisa si
 * toca avanzar de fase, así que no hace falta ningún proceso en segundo plano.
 */
require_once __DIR__ . '/db.php';
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

// ======================= CONFIGURACIÓN (cámbiala aquí) =======================
// Estos 4 valores son los de ARRANQUE: en el lobby el anfitrión los cambia y se guardan en la tabla partidas.
const TOTAL_RONDAS        = 3;
const SEGUNDOS_CUENTA     = 5;     // cuenta regresiva antes de cada ronda
const SEGUNDOS_CREAR      = 150;   // tiempo para hornear el momo
const MAX_REROLLS         = 10;    // rerolls por jugador en cada ronda
const MODO_MEMES          = 2;     // 2 = dos momos (arriba y abajo), 1 = un solo momo

// Opciones permitidas en la configuración del lobby (el servidor rechaza cualquier otro valor)
const OPCIONES_CONFIG = [
    'total_rondas' => [1, 3, 5, 7, 9],
    'seg_crear'    => [60, 90, 120, 150, 180, 210, 240, 270],
    'max_rerolls'  => [5, 10, 12, 15, 20],
    'modo_memes'   => [1, 2],
];
const GRACIA_CREAR        = 3;     // margen para que llegue el último autoguardado
const SEGUNDOS_VOTO       = 20;    // tiempo para votar cada meme
const SEGUNDOS_RESULTADOS = 20;    // pantalla de resultados de cada ronda
const PUNTOS_POR_MEME     = 1000;  // puntos totales que reparten los votantes
const PAPU_BONUS_MEME     = 0.10;  // +10% al meme por cada Papu Momo (solo si el meme salió positivo)
const PAPU_PREMIO_VOTANTE = 0.15;  // el que dio Papu Momo a un meme positivo gana 15% de sus puntos
const PAPU_CASTIGO_VOTANTE= 0.20;  // ...y si el meme salió negativo pierde 20% de sus puntos
const MIN_JUGADORES       = 2;     // pon 1 si quieres probar tú solo
const MIN_IMAGENES        = 2;     // mínimo de imágenes en el pool para poder jugar
const MAX_TEXTO           = 140;
const INACTIVO_SEG        = 15;    // sin señales por tanto tiempo = ya no esperamos a ese jugador
const CARPETA_POOL        = 'memes_pool/';
// =============================================================================

function json_out($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function partida_por_codigo(PDO $pdo, string $codigo) {
    $st = $pdo->prepare("SELECT * FROM partidas WHERE codigo_sala = ?");
    $st->execute([$codigo]);
    return $st->fetch();
}

function es_participante(PDO $pdo, int $pid, int $uid): bool {
    $st = $pdo->prepare("SELECT 1 FROM juego_jugadores WHERE partida_id = ? AND usuario_id = ?");
    $st->execute([$pid, $uid]);
    return (bool)$st->fetchColumn();
}

function contar_participantes(PDO $pdo, int $pid): int {
    $st = $pdo->prepare("SELECT COUNT(*) FROM juego_jugadores WHERE partida_id = ?");
    $st->execute([$pid]);
    return (int)$st->fetchColumn();
}

function url_imagen($archivo) {
    return $archivo ? CARPETA_POOL . $archivo : null;
}

// ----------------------------------------------------------------------------
//  AVANCE DE FASES
// ----------------------------------------------------------------------------

/** ¿Hay que pasar a la siguiente fase? (por tiempo, o antes si ya todos terminaron) */
function debe_avanzar(PDO $pdo, array $p): bool {
    $now = time();
    $pid = (int)$p['id'];
    $limite_activo = $now - INACTIVO_SEG;

    switch ($p['fase']) {
        case 'cuenta':
        case 'resultados':
            return $now >= (int)$p['fase_fin'];

        case 'creando':
            if ($now >= (int)$p['fase_fin'] + GRACIA_CREAR) return true;
            // Adelantar si todos los jugadores activos ya confirmaron
            $st = $pdo->prepare("SELECT COUNT(*) FROM juego_jugadores WHERE partida_id = ? AND ultimo_ping >= ?");
            $st->execute([$pid, $limite_activo]);
            if ((int)$st->fetchColumn() === 0) return false;
            $st = $pdo->prepare("SELECT COUNT(*) FROM memes m
                                 JOIN juego_jugadores j ON j.partida_id = m.partida_id AND j.usuario_id = m.usuario_id
                                 WHERE m.partida_id = ? AND m.ronda = ? AND m.confirmado = 0 AND j.ultimo_ping >= ?");
            $st->execute([$pid, (int)$p['ronda_actual'], $limite_activo]);
            return (int)$st->fetchColumn() === 0;

        case 'votando':
            if ($now >= (int)$p['fase_fin']) return true;
            $orden = json_decode($p['orden_memes'] ?? '[]', true) ?: [];
            $meme_id = $orden[(int)$p['meme_idx']] ?? null;
            if (!$meme_id) return true;
            // Adelantar si todos los votantes activos (menos el autor) ya votaron
            $st = $pdo->prepare("SELECT usuario_id FROM memes WHERE id = ?");
            $st->execute([$meme_id]);
            $autor = (int)$st->fetchColumn();
            $st = $pdo->prepare("SELECT COUNT(*) FROM juego_jugadores j
                                 WHERE j.partida_id = ? AND j.ultimo_ping >= ? AND j.usuario_id <> ?");
            $st->execute([$pid, $limite_activo, $autor]);
            if ((int)$st->fetchColumn() === 0) return false;
            $st = $pdo->prepare("SELECT COUNT(*) FROM juego_jugadores j
                                 WHERE j.partida_id = ? AND j.ultimo_ping >= ? AND j.usuario_id <> ?
                                   AND NOT EXISTS (SELECT 1 FROM votos v WHERE v.meme_id = ? AND v.votante_id = j.usuario_id)");
            $st->execute([$pid, $limite_activo, $autor, $meme_id]);
            return (int)$st->fetchColumn() === 0;
    }
    return false;
}

/** Revisa (con bloqueo) si toca avanzar y lo hace. Segura aunque 10 clientes llamen a la vez. */
function avanzar_partida(PDO $pdo, int $pid): void {
    for ($i = 0; $i < 6; $i++) {
        $st = $pdo->prepare("SELECT * FROM partidas WHERE id = ?");
        $st->execute([$pid]);
        $p = $st->fetch();
        if (!$p || $p['estado'] !== 'jugando' || !debe_avanzar($pdo, $p)) return;

        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare("SELECT * FROM partidas WHERE id = ? FOR UPDATE");
            $st->execute([$pid]);
            $p = $st->fetch();
            if ($p['estado'] === 'jugando' && debe_avanzar($pdo, $p)) {
                transicion($pdo, $p);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}

function transicion(PDO $pdo, array $p): void {
    $pid   = (int)$p['id'];
    $ronda = (int)$p['ronda_actual'];
    $now   = time();

    switch ($p['fase']) {
        case 'cuenta':
            crear_memes_ronda($pdo, $pid, $ronda, (int)$p['modo_memes']);
            $pdo->prepare("UPDATE partidas SET fase='creando', fase_fin=? WHERE id=?")
                ->execute([$now + (int)$p['seg_crear'], $pid]);
            break;

        case 'creando':
            // Lo que alcanzaron a escribir queda como meme final
            $pdo->prepare("UPDATE memes SET confirmado = 1 WHERE partida_id = ? AND ronda = ?")->execute([$pid, $ronda]);
            $st = $pdo->prepare("SELECT id FROM memes WHERE partida_id = ? AND ronda = ?");
            $st->execute([$pid, $ronda]);
            $ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
            shuffle($ids); // orden totalmente aleatorio
            $pdo->prepare("UPDATE partidas SET fase='votando', meme_idx=0, orden_memes=?, fase_fin=? WHERE id=?")
                ->execute([json_encode($ids), $now + SEGUNDOS_VOTO, $pid]);
            break;

        case 'votando':
            $orden = json_decode($p['orden_memes'] ?? '[]', true) ?: [];
            $idx = (int)$p['meme_idx'] + 1;
            if ($idx < count($orden)) {
                $pdo->prepare("UPDATE partidas SET meme_idx=?, fase_fin=? WHERE id=?")
                    ->execute([$idx, $now + SEGUNDOS_VOTO, $pid]);
            } else {
                finalizar_ronda($pdo, $pid, $ronda);
                $pdo->prepare("UPDATE partidas SET fase='resultados', fase_fin=? WHERE id=?")
                    ->execute([$now + SEGUNDOS_RESULTADOS, $pid]);
            }
            break;

        case 'resultados':
            if ($ronda < (int)$p['total_rondas']) {
                $pdo->prepare("UPDATE partidas SET ronda_actual=?, fase='cuenta', fase_fin=?, meme_idx=0, orden_memes=NULL WHERE id=?")
                    ->execute([$ronda + 1, $now + SEGUNDOS_CUENTA, $pid]);
            } else {
                $pdo->prepare("UPDATE partidas SET estado='terminada', fase='final', fase_fin=0 WHERE id=?")->execute([$pid]);
            }
            break;
    }
}

/** Reparte dos imágenes aleatorias a cada jugador para la ronda */
function crear_memes_ronda(PDO $pdo, int $pid, int $ronda, int $modo = 2): void {
    $imgs = array_map('intval', $pdo->query("SELECT id FROM imagenes_meme")->fetchAll(PDO::FETCH_COLUMN));
    $st = $pdo->prepare("SELECT usuario_id FROM juego_jugadores WHERE partida_id = ?");
    $st->execute([$pid]);
    $ins = $pdo->prepare("INSERT IGNORE INTO memes (partida_id, ronda, usuario_id, img_arriba_id, img_abajo_id)
                          VALUES (?, ?, ?, ?, ?)");
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $uid) {
        shuffle($imgs);
        $a = $imgs[0] ?? null;
        $b = ($modo === 2) ? ($imgs[1] ?? $a) : null; // en modo de un solo momo no hay imagen de abajo
        $ins->execute([$pid, $ronda, (int)$uid, $a, $b]);
    }
}

/**
 * Calcula los puntos de la ronda.
 *  - Cada votante vale 1000 / (jugadores - 1)  (así si TODOS votan positivo el meme saca 1000).
 *  - Me gustó = +valor, No me gustó = -valor, Meh = 0.
 *  - Papu Momo: cada uno suma +10% al meme (solo si salió positivo).
 *  - El votante que dio Papu Momo: meme positivo => +15% de sus puntos; meme negativo => -20% de sus puntos.
 */
function finalizar_ronda(PDO $pdo, int $pid, int $ronda): void {
    $n = contar_participantes($pdo, $pid);
    $st = $pdo->prepare("SELECT id FROM memes WHERE partida_id = ? AND ronda = ?");
    $st->execute([$pid, $ronda]);
    $memes = $st->fetchAll(PDO::FETCH_COLUMN);

    $cuenta = $pdo->prepare("SELECT COALESCE(SUM(valor = 1),0) AS likes, COALESCE(SUM(valor = -1),0) AS dislikes,
                                    COALESCE(SUM(papu),0) AS papus FROM votos WHERE meme_id = ?");
    $upd_meme = $pdo->prepare("UPDATE memes SET puntos_base = ?, puntos_finales = ? WHERE id = ?");
    $upd_papu = $pdo->prepare("UPDATE votos SET puntos_papu = ? WHERE meme_id = ? AND papu = 1");

    foreach ($memes as $meme_id) {
        $cuenta->execute([$meme_id]);
        $c = $cuenta->fetch();
        $base = $n > 1 ? (int)round(PUNTOS_POR_MEME * ((int)$c['likes'] - (int)$c['dislikes']) / ($n - 1)) : 0;
        $bonus = ($base > 0 && (int)$c['papus'] > 0) ? (int)round($base * PAPU_BONUS_MEME * (int)$c['papus']) : 0;
        $final = $base + $bonus;

        $upd_meme->execute([$base, $final, $meme_id]);

        if ($final > 0)      $pp = (int)round($final * PAPU_PREMIO_VOTANTE);
        elseif ($final < 0)  $pp = -(int)round(abs($final) * PAPU_CASTIGO_VOTANTE);
        else                 $pp = 0;
        $upd_papu->execute([$pp, $meme_id]);
    }
}

/** Puntos por jugador y ronda (solo cuentan rondas ya finalizadas) */
function calcular_puntajes(PDO $pdo, int $pid, int $total_rondas = TOTAL_RONDAS): array {
    $st = $pdo->prepare("SELECT j.usuario_id, u.username, u.foto_perfil
                         FROM juego_jugadores j JOIN usuarios u ON u.id = j.usuario_id
                         WHERE j.partida_id = ?");
    $st->execute([$pid]);
    $res = [];
    foreach ($st->fetchAll() as $r) {
        $rondas = [];
        for ($i = 1; $i <= $total_rondas; $i++) $rondas[$i] = ['meme' => 0, 'papu' => 0];
        $res[(int)$r['usuario_id']] = ['id' => (int)$r['usuario_id'], 'username' => $r['username'],
                                       'foto' => $r['foto_perfil'], 'rondas' => $rondas, 'total' => 0];
    }
    $st = $pdo->prepare("SELECT usuario_id, ronda, COALESCE(puntos_finales,0) AS pts FROM memes WHERE partida_id = ?");
    $st->execute([$pid]);
    foreach ($st->fetchAll() as $r) {
        $u = (int)$r['usuario_id'];
        if (isset($res[$u])) $res[$u]['rondas'][(int)$r['ronda']]['meme'] = (int)$r['pts'];
    }
    $st = $pdo->prepare("SELECT v.votante_id, m.ronda, SUM(v.puntos_papu) AS pts
                         FROM votos v JOIN memes m ON m.id = v.meme_id
                         WHERE m.partida_id = ? GROUP BY v.votante_id, m.ronda");
    $st->execute([$pid]);
    foreach ($st->fetchAll() as $r) {
        $u = (int)$r['votante_id'];
        if (isset($res[$u])) $res[$u]['rondas'][(int)$r['ronda']]['papu'] = (int)$r['pts'];
    }
    foreach ($res as &$u) {
        foreach ($u['rondas'] as $r) $u['total'] += $r['meme'] + $r['papu'];
    }
    return $res;
}
