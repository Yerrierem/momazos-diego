<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}
$codigo = isset($_GET['sala']) ? $_GET['sala'] : '';
if ($codigo === '') {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Momazos - Sala <?php echo htmlspecialchars($codigo); ?></title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #1a1a2e; color: #fff; margin: 0; padding: 16px; }
        .top { display: flex; justify-content: space-between; align-items: center; background: #16213e; padding: 12px 24px; border-radius: 8px; margin-bottom: 18px; flex-wrap: wrap; gap: 8px; }
        .top .sala { color: #e94560; font-weight: bold; letter-spacing: 2px; }
        .top .ronda { font-size: 18px; font-weight: bold; }

        .layout { display: grid; grid-template-columns: 330px 1fr; gap: 24px; align-items: start; }
        @media (max-width: 800px) { .layout, .layout-res { grid-template-columns: 1fr !important; } }
        .panel { background: #16213e; border-radius: 10px; padding: 20px; text-align: center; }
        .panel h2 { margin: 0 0 6px; }
        .panel p { color: #b8c0d8; font-size: 14px; margin: 6px 0; }
        .timer { font-size: 64px; font-weight: bold; line-height: 1; margin: 10px 0; }
        .timer.urgente { color: #ff5252; animation: parpadeo 1s infinite; }
        @keyframes parpadeo { 50% { opacity: .55; } }

        button.btn { display: block; width: 100%; margin-top: 10px; padding: 12px; border: none; border-radius: 8px; font-size: 16px; font-weight: bold; cursor: pointer; color: #fff; background: #0f3460; }
        button.btn:hover:not(:disabled) { filter: brightness(1.25); }
        button.btn:disabled { opacity: .45; cursor: not-allowed; }
        button.ok { background: #28a745; }
        button.gusto { background: #28a745; } button.meh { background: #6c757d; } button.nogusto { background: #dc3545; }
        button.papu { background: #ff9800; color: #1a1a2e; }
        button.sel { outline: 4px solid #fff; transform: scale(1.03); }

        /* ---------- El meme ---------- */
        .meme-wrap { display: flex; justify-content: center; }
        .meme { width: 100%; max-width: 480px; background: #fff; border-radius: 6px; overflow: hidden; color: #000; box-shadow: 0 6px 24px rgba(0,0,0,.5); }
        .meme.chico { max-width: 340px; }
        .meme-texto { background: #fff; min-height: 76px; padding: 8px 12px; display: flex; align-items: center; justify-content: center; text-align: center; font-weight: bold; font-size: 20px; word-break: break-word; }
        .meme.chico .meme-texto { min-height: 56px; font-size: 16px; }
        .meme-texto.vacio::before { content: '…'; color: #bbb; }
        .meme-texto textarea { width: 100%; border: 2px dashed #bbb; border-radius: 6px; resize: none; font: bold 20px Arial, sans-serif; text-align: center; padding: 8px; color: #000; background: #fafafa; }
        .meme-texto textarea:focus { outline: none; border-color: #0f3460; background: #fff; }
        .meme-texto textarea:read-only { background: #eee; border-style: solid; color: #444; }
        .meme-img { background: #111; display: flex; align-items: center; justify-content: center; min-height: 160px; color: #888; }
        .meme-img img { display: block; width: 100%; max-height: 300px; object-fit: contain; }
        .meme.chico .meme-img img { max-height: 200px; }

        /* ---------- Cuenta regresiva ---------- */
        .cuenta { text-align: center; padding: 80px 0; }
        .cuenta .timer { font-size: 160px; }

        /* ---------- Resultados ---------- */
        .layout-res { display: grid; grid-template-columns: 1fr 360px; gap: 24px; align-items: start; }
        .lista-memes { display: flex; flex-direction: column; gap: 18px; }
        .fila-meme { display: flex; gap: 18px; background: #16213e; border-radius: 10px; padding: 14px; align-items: center; flex-wrap: wrap; }
        .fila-meme .pos { font-size: 40px; font-weight: bold; min-width: 60px; text-align: center; }
        .fila-meme .info { flex: 1; min-width: 180px; }
        .autor { display: flex; align-items: center; gap: 10px; font-weight: bold; font-size: 18px; }
        .autor img, .marcador img { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; border: 2px solid #e94560; }
        .pts { font-size: 38px; font-weight: bold; margin: 8px 0; }
        .pos-pts { color: #6dff8c; } .neg-pts { color: #ff6b6b; } .cero-pts { color: #ccc; }
        .reacciones { font-size: 16px; color: #dde; }
        .desglose { font-size: 13px; color: #9aa5c8; margin-top: 4px; }
        .marcador { background: #16213e; border-radius: 10px; padding: 16px; position: sticky; top: 16px; }
        .marcador h3 { margin: 0 0 10px; text-align: center; }
        .marcador-fila { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid #243056; }
        .marcador-fila:last-child { border-bottom: none; }
        .marcador-fila .nom { flex: 1; font-weight: bold; overflow: hidden; text-overflow: ellipsis; }
        .marcador-fila .sub { font-size: 12px; font-weight: normal; color: #9aa5c8; }
        .marcador-fila .tot { font-size: 20px; font-weight: bold; }
        .yo { background: rgba(233,69,96,.15); border-radius: 6px; }

        /* ---------- Final ---------- */
        .final { max-width: 700px; margin: 0 auto; text-align: center; }
        .final h1 { font-size: 40px; }
        .podio-fila { display: flex; align-items: center; gap: 14px; background: #16213e; border-radius: 10px; padding: 14px 18px; margin: 12px 0; text-align: left; }
        .podio-fila.p1 { background: linear-gradient(90deg, #8a6d0b, #16213e); border: 2px solid #ffd700; }
        .podio-fila .medalla { font-size: 34px; min-width: 50px; text-align: center; }
        .podio-fila img { width: 56px; height: 56px; border-radius: 50%; object-fit: cover; border: 3px solid #e94560; }
        .podio-fila .nom { flex: 1; font-size: 20px; font-weight: bold; }
        .podio-fila .nom small { display: block; font-size: 12px; font-weight: normal; color: #9aa5c8; }
        .podio-fila .tot { font-size: 28px; font-weight: bold; }
        .botones-final a { display: inline-block; margin: 14px 8px; padding: 12px 26px; border-radius: 8px; font-weight: bold; text-decoration: none; color: #fff; background: #e94560; }
        .aviso { color: #ffc107; font-weight: bold; margin-top: 10px; min-height: 20px; }
        .conexion { position: fixed; bottom: 10px; right: 10px; background: #dc3545; padding: 6px 12px; border-radius: 6px; display: none; font-size: 13px; }
    </style>
</head>
<body>

<div class="top">
    <span class="sala">Sala <?php echo htmlspecialchars($codigo); ?></span>
    <span class="ronda" id="hdr-ronda">Cargando…</span>
    <span id="hdr-fase"></span>
</div>

<main id="vista"></main>
<div class="conexion" id="conexion">Reconectando…</div>

<script>
const SALA = <?php echo json_encode($codigo); ?>;
const MAX_TEXTO = 140;

// ------------------------------------------------------------------ utilidades
function el(tag, cls, txt) {
    const e = document.createElement(tag);
    if (cls) e.className = cls;
    if (txt !== undefined) e.textContent = txt;
    return e;
}
async function api(accion, datos = {}) {
    const body = new URLSearchParams({ accion, sala: SALA, ...datos });
    const r = await fetch('juego_api.php', { method: 'POST', body });
    return r.json();
}
function fmtPts(n) { return (n > 0 ? '+' : '') + n.toLocaleString('es-MX'); }
function clasePts(n) { return n > 0 ? 'pos-pts' : (n < 0 ? 'neg-pts' : 'cero-pts'); }
function setImg(wrapper, url) {
    wrapper.innerHTML = '';
    if (url) { const i = el('img'); i.src = url; i.alt = ''; wrapper.appendChild(i); }
    else wrapper.textContent = 'Sin imagen';
}

/**
 * Construye el meme:   [texto 1] / imagen 1 / [texto 2] / imagen 2
 * editable=true -> los textos son cajas para escribir.
 */
function crearMeme(m, editable, chico, modo = 2) {
    const root = el('div', 'meme' + (chico ? ' chico' : ''));
    const caja = (txt, ph) => {
        const box = el('div', 'meme-texto');
        let ta = null;
        if (editable) {
            ta = el('textarea');
            ta.rows = 2; ta.maxLength = MAX_TEXTO; ta.placeholder = ph; ta.value = txt || '';
            box.appendChild(ta);
        } else {
            box.textContent = txt || '';
            if (!txt) box.classList.add('vacio');
        }
        return { box, ta };
    };
    // modo 2 = dos momos (When / but). modo 1 = un solo momo: una caja de texto y una imagen.
    const c1 = caja(m.ta, modo === 1 ? 'Escribe tu momo' : 'When');
    const i1 = el('div', 'meme-img'); setImg(i1, m.arriba);
    if (modo === 1) {
        root.append(c1.box, i1);
        return { root, ta1: c1.ta, ta2: null, img1: i1, img2: null };
    }
    const c2 = caja(m.tb, 'but');
    const i2 = el('div', 'meme-img'); setImg(i2, m.abajo);
    root.append(c1.box, i1, c2.box, i2);
    return { root, ta1: c1.ta, ta2: c2.ta, img1: i1, img2: i2 };
}

// ------------------------------------------------------------------ estado global
let S = null;            // último estado del servidor
let offsetMs = 0;        // diferencia reloj servidor - reloj local
let finMs = 0;
let claveVista = '';
let ctx = {};            // referencias de la vista actual

function restante() { return Math.max(0, Math.ceil((finMs - (Date.now() + offsetMs)) / 1000)); }

setInterval(() => {
    if (!S) return;
    const rem = restante();
    document.querySelectorAll('.js-timer').forEach(e => {
        e.textContent = rem;
        e.classList.toggle('urgente', rem <= 10 && S.fase !== 'cuenta');
    });
    // Se acabó el tiempo de crear: bloquear y mandar lo que haya escrito
    if (S.fase === 'creando' && rem === 0 && ctx.editando) bloquearCreacion(false);
}, 200);

// ------------------------------------------------------------------ polling
async function poll() {
    let siguiente = 1000;
    try {
        const r = await fetch('juego_api.php?accion=estado&sala=' + encodeURIComponent(SALA), { cache: 'no-store' });
        const d = await r.json();
        document.getElementById('conexion').style.display = 'none';
        if (!d.ok) {
            if (d.redirigir) { location.href = d.redirigir; return; }
        } else {
            offsetMs = d.ahora * 1000 - Date.now();
            finMs = d.fin * 1000;
            S = d;
            const clave = [d.fase, d.ronda, d.fase === 'votando' ? d.voto?.meme?.id : ''].join('-');
            if (clave !== claveVista) { claveVista = clave; construirVista(d); }
            else actualizarVista(d);
            if (d.fase === 'final') return; // ya no hace falta seguir preguntando
        }
    } catch (e) {
        document.getElementById('conexion').style.display = 'block';
    }
    setTimeout(poll, siguiente);
}

function cabecera(d) {
    document.getElementById('hdr-ronda').textContent =
        d.fase === 'final' ? 'Partida terminada' : `Ronda ${d.ronda} de ${d.total_rondas}`;
    const nombres = { cuenta: 'Prepárate…', creando: 'Crea tu momo', votando: 'Votación', resultados: 'Resultados', final: '' };
    document.getElementById('hdr-fase').textContent = nombres[d.fase] || '';
}

function construirVista(d) {
    cabecera(d);
    ctx = {};
    const v = document.getElementById('vista');
    v.innerHTML = '';
    if (d.fase === 'cuenta')           vistaCuenta(v, d);
    else if (d.fase === 'creando')     vistaCrear(v, d);
    else if (d.fase === 'votando')     vistaVotar(v, d);
    else if (d.fase === 'resultados')  vistaResultados(v, d);
    else if (d.fase === 'final')       vistaFinal(v, d);
}

function actualizarVista(d) {
    if (d.fase === 'creando' && ctx.listos) {
        ctx.listos.textContent = `Listos: ${d.listos}/${d.n}`;
    } else if (d.fase === 'votando' && d.voto && ctx.progreso) {
        ctx.progreso.textContent = `Han votado ${d.voto.votaron} de ${d.voto.votantes}`;
    }
}

// ------------------------------------------------------------------ CUENTA
function vistaCuenta(v, d) {
    const c = el('div', 'cuenta');
    c.append(el('h2', '', d.ronda === 1 ? '¡La partida va a comenzar!' : `Ronda ${d.ronda}`),
             el('div', 'timer js-timer', ''),
             el('p', '', `Tendrás ${d.seg_crear} segundos para armar tu momo`));
    v.appendChild(c);
}

// ------------------------------------------------------------------ CREAR MEME
let temporizadorGuardado = null;

function vistaCrear(v, d) {
    const modo = d.modo;
    const meme = crearMeme(d.meme || { ta: '', tb: '' }, true, false, modo);
    ctx.editando = !d.confirmado;
    ctx.restantes = d.max_rerolls - d.rerolls_usados;
    ctx.meme = meme;

    const panel = el('div', 'panel');
    const timer = el('div', 'timer js-timer', '');
    const bTop = el('button', 'btn', modo === 1 ? 'Reroll imágen' : 'Reroll imágen de ARRIBA');
    const bBot = modo === 2 ? el('button', 'btn', 'Reroll imágen de ABAJO') : null;
    const bOk  = el('button', 'btn ok', 'Momo terminado');
    ctx.infoRerolls = el('p', '', '');
    ctx.listos = el('p', '', `Listos: ${d.listos}/${d.n}`);
    ctx.aviso = el('div', 'aviso', '');
    ctx.botones = [bTop, bBot, bOk].filter(Boolean);

    panel.append(el('h2', '', 'Crea tu momo'),
        el('p', '', 'Arma tu momazo'),
        timer, ...[bTop, bBot].filter(Boolean), ctx.infoRerolls, bOk, ctx.aviso, ctx.listos);

    const wrap = el('div', 'meme-wrap');
    wrap.appendChild(meme.root);
    const lay = el('div', 'layout');
    lay.append(panel, wrap);
    v.appendChild(lay);

    const textos = () => ({ ta: meme.ta1.value, tb: meme.ta2 ? meme.ta2.value : '' });
    ctx.textos = textos;

    // Autoguardado (el servidor se queda con lo último que llegó)
    const programarGuardado = () => {
        clearTimeout(temporizadorGuardado);
        temporizadorGuardado = setTimeout(() => { if (ctx.editando) api('guardar', textos()); }, 500);
    };
    meme.ta1.addEventListener('input', programarGuardado);
    if (meme.ta2) meme.ta2.addEventListener('input', programarGuardado);

    // Los rerolls son limitados: el contador y los botones se actualizan con lo que responde el servidor
    const pintarRerolls = () => {
        ctx.infoRerolls.textContent = `Rerolls que te quedan: ${ctx.restantes} de ${d.max_rerolls}`;
        if (ctx.editando) [bTop, bBot].filter(Boolean).forEach(b => b.disabled = ctx.restantes <= 0);
    };
    const reroll = async (cual, boton) => {
        if (!ctx.editando || ctx.restantes <= 0) return;
        boton.disabled = true;
        try {
            const r = await api('reroll', { cual });
            if (typeof r.restantes === 'number') ctx.restantes = r.restantes;
            if (r.ok) { setImg(cual === 'arriba' ? meme.img1 : meme.img2, r.url); ctx.aviso.textContent = ''; }
            else ctx.aviso.textContent = r.error || '';
        } catch (e) {
            ctx.aviso.textContent = 'Error de conexión.';
        } finally { pintarRerolls(); }
    };
    bTop.addEventListener('click', () => reroll('arriba', bTop));
    if (bBot) bBot.addEventListener('click', () => reroll('abajo', bBot));
    pintarRerolls();

    bOk.addEventListener('click', () => {
        if (!confirm('¿Seguro? Después de confirmar ya no podrás modificar tu momo.')) return;
        bloquearCreacion(true);
    });

    if (d.confirmado) bloquearCreacion(false, true);
}

/** Bloquea el editor. confirmar=true -> avisa al servidor que terminaste. */
async function bloquearCreacion(confirmar, soloVisual) {
    if (!ctx.meme) return;
    const yaBloqueado = !ctx.editando;
    ctx.editando = false;
    clearTimeout(temporizadorGuardado);
    ctx.meme.ta1.readOnly = true;
    if (ctx.meme.ta2) ctx.meme.ta2.readOnly = true;
    ctx.botones.forEach(b => b.disabled = true);
    ctx.aviso.textContent = confirmar || soloVisual ? 'Ya acabaste de hornear. Esperando a los demás…' : '¡TIEMPO!';
    if (soloVisual || yaBloqueado) return;
    try { await api('guardar', { ...ctx.textos(), confirmar: confirmar ? 1 : 0 }); } catch (e) {}
}

// ------------------------------------------------------------------ VOTAR
function vistaVotar(v, d) {
    const vt = d.voto;
    if (!vt) { v.appendChild(el('p', '', 'Cargando meme…')); return; }

    const meme = crearMeme(vt.meme, false, false, d.modo);
    const panel = el('div', 'panel');
    panel.append(el('h2', '', `Meme ${vt.idx + 1} de ${vt.total}`),
                 el('div', 'timer js-timer', ''));

    ctx.progreso = el('p', '', `Han votado ${vt.votaron} de ${vt.votantes}`);

    if (vt.es_mio) {
        panel.append(el('p', '', 'Espera a que los demás voten a tu meme'), ctx.progreso);
    } else {
        let valor = vt.mi_voto ? vt.mi_voto.valor : null;
        let papu  = vt.mi_voto ? vt.mi_voto.papu : 0;
        const bG = el('button', 'btn gusto',   '👍 Me gustó');
        const bM = el('button', 'btn meh',     '😐 Meh');
        const bN = el('button', 'btn nogusto', '👎 No me gustó');
        const bP = el('button', 'btn papu',    '🔥 PAPU MOMO');
        const aviso = el('div', 'aviso', '');

        const pintar = () => {
            bG.classList.toggle('sel', valor === 1);
            bM.classList.toggle('sel', valor === 0);
            bN.classList.toggle('sel', valor === -1);
            bP.classList.toggle('sel', !!papu);
            bP.disabled = (valor === null);
        };
        const enviar = async () => {
            const r = await api('votar', { meme_id: vt.meme.id, valor, papu });
            aviso.textContent = r.ok ? '✔ Voto guardado (puedes cambiarlo mientras haya tiempo)' : (r.error || '');
        };
        bG.onclick = () => { valor = 1;  pintar(); enviar(); };
        bM.onclick = () => { valor = 0;  pintar(); enviar(); };
        bN.onclick = () => { valor = -1; pintar(); enviar(); };
        bP.onclick = () => { if (valor === null) return; papu = papu ? 0 : 1; pintar(); enviar(); };
        pintar();

        panel.append(bG, bM, bN, bP, aviso,
            ctx.progreso);
    }

    const wrap = el('div', 'meme-wrap');
    wrap.appendChild(meme.root);
    const lay = el('div', 'layout');
    lay.append(panel, wrap);
    v.appendChild(lay);
}

// ------------------------------------------------------------------ RESULTADOS DE RONDA
function marcador(d, titulo) {
    const box = el('div', 'marcador');
    box.appendChild(el('h3', '', titulo));
    const lista = [...d.puntajes].sort((a, b) => b.total - a.total);
    lista.forEach((u, i) => {
        const f = el('div', 'marcador-fila' + (u.id === d.yo ? ' yo' : ''));
        const img = el('img'); img.src = 'uploads/' + u.foto; img.alt = '';
        const nom = el('div', 'nom', `${i + 1}. ${u.username}`);
        const r = u.rondas[d.ronda];
        const sub = el('div', 'sub', `Ronda: ${fmtPts(r.meme + r.papu)}  (meme ${fmtPts(r.meme)}, Papu ${fmtPts(r.papu)})`);
        nom.appendChild(sub);
        const tot = el('div', 'tot ' + clasePts(u.total), u.total.toLocaleString('es-MX'));
        f.append(img, nom, tot);
        box.appendChild(f);
    });
    return box;
}

function vistaResultados(v, d) {
    const lay = el('div', 'layout-res');
    const izq = el('div');
    const cab = el('div', 'panel');
    cab.append(el('h2', '', `Resultados de la ronda ${d.ronda}`),
        el('p', '', d.ronda < d.total_rondas ? 'La siguiente ronda empieza en…' : 'Ranking final en…'),
        el('div', 'timer js-timer', ''));
    cab.style.marginBottom = '18px';
    izq.appendChild(cab);

    const lista = el('div', 'lista-memes');
    d.memes.forEach((m, i) => {
        const fila = el('div', 'fila-meme');
        fila.appendChild(el('div', 'pos', '#' + (i + 1)));
        fila.appendChild(crearMeme(m, false, true, d.modo).root);

        const info = el('div', 'info');
        const autor = el('div', 'autor');
        const img = el('img'); img.src = 'uploads/' + m.foto; img.alt = '';
        autor.append(img, el('span', '', m.autor));
        info.append(autor,
            el('div', 'pts ' + clasePts(m.final), fmtPts(m.final) + ' pts'),
            el('div', 'reacciones', `👍 ${m.likes}   😐 ${m.mehs}   👎 ${m.dislikes}   🔥 ${m.papus}`));
        if (m.final !== m.base) info.appendChild(el('div', 'desglose', `Votos: ${fmtPts(m.base)} · Bonus Papu Momo: ${fmtPts(m.final - m.base)}`));
        fila.appendChild(info);
        lista.appendChild(fila);
    });
    izq.appendChild(lista);

    lay.append(izq, marcador(d, 'Puntuaciones'));
    v.appendChild(lay);
}

// ------------------------------------------------------------------ FINAL
function vistaFinal(v, d) {
    const f = el('div', 'final');
    f.appendChild(el('h1', '', '🏆 ¡Fin de la partida!'));
    const lista = [...d.puntajes].sort((a, b) => b.total - a.total);
    const medallas = ['🥇', '🥈', '🥉'];
    lista.forEach((u, i) => {
        const fila = el('div', 'podio-fila' + (i === 0 ? ' p1' : ''));
        const img = el('img'); img.src = 'uploads/' + u.foto; img.alt = '';
        const nom = el('div', 'nom', u.username + (u.id === d.yo ? ' (tú)' : ''));
        const det = Object.keys(u.rondas).map(k => `R${k}: ${fmtPts(u.rondas[k].meme + u.rondas[k].papu)}`).join(' · ');
        nom.appendChild(el('small', '', det));
        fila.append(el('div', 'medalla', medallas[i] || `#${i + 1}`), img, nom,
                    el('div', 'tot ' + clasePts(u.total), u.total.toLocaleString('es-MX')));
        f.appendChild(fila);
    });
    const b = el('div', 'botones-final');
    const a1 = el('a', '', '🏠 Volver al inicio'); a1.href = 'index.php';
    const a2 = el('a', '', '➕ Nueva partida'); a2.href = 'crear_partida.php';
    b.append(a1, a2);
    f.appendChild(b);
    v.appendChild(f);
}

poll();
</script>
</body>
</html>
