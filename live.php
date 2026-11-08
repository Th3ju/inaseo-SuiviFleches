<?php
/**
 * Flèches en direct — écran plein écran pour vidéoprojecteur ou TV.
 *
 * Page autonome : elle n'inclut pas le template IANSEO et interroge data.php
 * en boucle. Les archers sont regroupés par division et classe ; chaque ligne
 * affiche le nom, le prénom et le score courant.
 *
 * Paramètres :
 *   ?tour=<ToId>  concours à afficher (sinon celui ouvert en session)
 *   ?demo=1       jeu de données fictif, pour régler l'écran avant le concours
 */

$suiviflBootstrap = dirname(dirname(__FILE__)) . '/config.php';
if (!is_file($suiviflBootstrap)) {
    $suiviflBootstrap = dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php';
}
require_once($suiviflBootstrap);
require_once('Common/Fun_Various.inc.php');
require_once(dirname(__FILE__) . '/Lib/Fun_SuiviFleches.php');

$TourId = suivifl_TourId();
if (!$TourId) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="fr"><meta charset="utf-8">'
       . '<title>Flèches en direct</title>'
       . '<body style="margin:0;padding:2em;background:#0b1220;color:#eef3fb;'
       . 'font:16px/1.5 system-ui,sans-serif">'
       . '<p>Aucun concours sélectionné. Ouvrez un concours dans IANSEO, ou appelez '
       . 'cette page avec <code>?tour=&lt;numéro du concours&gt;</code>.</p>'
       . '</body></html>';
    exit;
}

$cfg  = suivifl_GetConfig($TourId);
$demo = !empty($_REQUEST['demo']) ? 1 : 0;
$dataUrl = $CFG->ROOT_DIR . 'Modules/Custom/' . basename(dirname(__FILE__))
         . '/data.php?tour=' . $TourId . ($demo ? '&demo=1' : '');

$TourRs = safe_r_sql(
    "SELECT ToName FROM Tournament WHERE ToId=" . StrSafe_DB($TourId)
);
$Tour = $TourRs ? safe_fetch($TourRs) : false;
$InitialTitle = $cfg['title'] !== ''
    ? $cfg['title']
    : ($Tour ? (string)$Tour->ToName : 'Flèches en direct');
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Flèches en direct</title>
<style>
:root {
    --bg:      #070d18;
    --bg-alt:  #0e1a2c;
    --panel:   #101d32;
    --line:    #1d2d47;
    --fg:      #f2f6fc;
    --muted:   #8fa4c4;
    --accent:  #ffc043;
    --ok:      #37d67a;
    --ko:      #ff4d5e;
}

/* Vidéoprojecteur avec éclairage ambiant : fond clair, texte et scores foncés. */
body[data-theme="light"] {
    --bg:      #f7f9fc;
    --bg-alt:  #e8edf4;
    --panel:   #ffffff;
    --line:    #b8c4d4;
    --fg:      #0a1220;
    --muted:   #45536a;
    --accent:  #8a4b00;
    --ok:      #087a3e;
    --ko:      #b71c1c;
}

/* Fort contraste sur fond noir. */
body[data-theme="contrast"] {
    --bg:      #000000;
    --bg-alt:  #111111;
    --panel:   #000000;
    --line:    #ffffff;
    --fg:      #ffffff;
    --muted:   #ffffff;
    --accent:  #ffe600;
    --ok:      #00ff66;
    --ko:      #ff3b30;
}

/* Plein jour / très fort éclairage : fond jaune très clair, noir, traits épais. */
body[data-theme="sunlight"] {
    --bg:      #fffdf0;
    --bg-alt:  #fff2a8;
    --panel:   #ffffff;
    --line:    #1a1a1a;
    --fg:      #000000;
    --muted:   #222222;
    --accent:  #5a2d00;
    --ok:      #005c25;
    --ko:      #a00000;
}
body[data-theme="sunlight"] .c-ath { font-weight: 700; }
body[data-theme="sunlight"] .c-score { font-weight: 900; }
body[data-theme="sunlight"] tbody td { border-bottom-width: .18vh; }
body[data-theme="sunlight"] h2 { border-bottom-width: .4vh; }
* { box-sizing: border-box; margin: 0; padding: 0; }
html, body { height: 100%; }
body {
    background: var(--bg);
    color: var(--fg);
    font-family: "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    cursor: none;
}

header {
    display: flex;
    align-items: center;
    gap: 1.5vw;
    padding: 1.2vh 2.2vw;
    background: var(--panel);
    border-bottom: .3vh solid var(--line);
    border-top: .5vh solid var(--accent);
    flex: none;
}
.title {
    font-size: 2.2vh;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: .08em;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 32%;
}
.event {
    flex: 1;
    min-width: 0;
    font-size: 3.4vh;
    font-weight: 700;
    color: var(--accent);
    text-align: center;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.meta {
    display: flex;
    align-items: center;
    gap: 1vw;
    font-size: 2.2vh;
    color: var(--muted);
    white-space: nowrap;
}
.dot {
    width: 1.2vh;
    height: 1.2vh;
    border-radius: 50%;
    background: var(--ok);
}
.dot.stale { background: var(--ko); }
.badge {
    font-size: 1.7vh;
    padding: .3vh .8vh;
    border-radius: .5vh;
    background: var(--ko);
    color: #fff;
    letter-spacing: .05em;
}

main {
    flex: 1;
    position: relative;
    overflow: hidden;
    padding: 0 2.2vw;
    min-height: 0;
}
#scroller {
    position: absolute;
    left: 2.2vw;
    right: 2.2vw;
    top: 0;
    will-change: transform;
}

section { padding-bottom: var(--gap); }
h2 {
    font-size: calc(var(--font-row) * 1.08);
    color: var(--accent);
    font-weight: 700;
    padding: calc(var(--row-h) * .42) 0 calc(var(--row-h) * .16);
    border-bottom: .3vh solid var(--line);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

table { width: 100%; border-collapse: collapse; table-layout: fixed; }
tbody td {
    padding: 0 .9vw;
    border-bottom: .1vh solid var(--line);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
tbody tr { height: var(--row-h); }
tbody tr:nth-child(even) { background: var(--bg-alt); }

.c-ath   { width: 62%; font-size: var(--font-row); font-weight: 600; }
.c-end   { width: 14%; text-align: center; color: var(--muted); font-weight: 600;
           font-size: calc(var(--font-row) * 0.78); font-variant-numeric: tabular-nums; }
.c-score { width: 24%; text-align: right; font-size: calc(var(--font-row) * 1.12);
           font-weight: 800; font-variant-numeric: tabular-nums; letter-spacing: .02em; }
.c-score.ok { color: var(--ok); }
.c-score.ko { color: var(--ko); }

.empty {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 4vw;
    font-size: 3.6vh;
    color: var(--muted);
    text-align: center;
}

footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.5vw;
    padding: 1vh 2.2vw;
    background: var(--panel);
    border-top: .3vh solid var(--line);
    font-size: 1.9vh;
    color: var(--muted);
    flex: none;
}
footer .keys { text-align: right; }

@media (max-width: 720px) {
    .title { display: none; }
    .event { font-size: 2.6vh; }
    .meta { font-size: 1.8vh; }
    footer { flex-wrap: wrap; font-size: 1.7vh; }
    footer .keys { display: none; }
}
</style>
</head>
<body data-theme="<?php echo htmlspecialchars($cfg['theme']); ?>">

<header>
    <div class="title" id="title"><?php echo htmlspecialchars($InitialTitle); ?></div>
    <div class="event" id="event">Flèches en direct</div>
    <div class="meta">
        <span class="badge" id="demoBadge"<?php echo $demo ? '' : ' hidden'; ?>>DÉMO</span>
        <span class="dot" id="dot"></span>
        <span id="clock">--:--</span>
    </div>
</header>

<main id="main"><div id="scroller"></div></main>

<footer>
    <span id="summary">&nbsp;</span>
    <span id="legend">Seuil : <?php echo (int)$cfg['threshold']; ?> pts</span>
    <span class="keys">Espace : pause &middot; &larr; &rarr; : catégorie &middot; F : plein écran</span>
</footer>

<script>
(function () {
    'use strict';

    var DATA_URL = <?php echo json_encode($dataUrl); ?>;

    var cfg    = {
        scrollsec: <?php echo (int)$cfg['scrollsec']; ?>,
        refresh:   <?php echo (int)$cfg['refresh']; ?>,
        rows:      <?php echo (int)$cfg['rows']; ?>,
        threshold: <?php echo (int)$cfg['threshold']; ?>
    };

    var paused  = false;
    var offset  = 0;      // décalage vertical courant, en pixels
    var loopH   = 0;      // hauteur d'une copie du contenu
    var lastOk  = 0;      // date de la dernière réponse valide
    var lastTs  = 0;      // horodatage de la dernière frame
    var anchors = [];     // position verticale de chaque titre de catégorie
    var refreshTimer = null;

    var $main     = document.getElementById('main');
    var $scroller = document.getElementById('scroller');
    var $event    = document.getElementById('event');
    var $dot      = document.getElementById('dot');

    function pad(n) { return (n < 10 ? '0' : '') + n; }

    function esc(s) {
        return String(s === null || s === undefined ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function tickClock() {
        var d = new Date();
        document.getElementById('clock').textContent = pad(d.getHours()) + ':' + pad(d.getMinutes());
    }

    // Taille du texte bornée par la hauteur de ligne voulue et par la largeur.
    function fitRows() {
        var h = Math.max(24, Math.floor($main.clientHeight / Math.max(cfg.rows, 1)));
        var f = Math.max(12, Math.min(Math.floor(h * 0.52),
                                      Math.floor($main.clientWidth * 0.031)));
        $main.style.setProperty('--row-h', h + 'px');
        $main.style.setProperty('--font-row', f + 'px');
        $main.style.setProperty('--gap', Math.floor(h * 0.65) + 'px');
    }

    function sectionHtml(section) {
        var rows = '';
        section.items.forEach(function (it) {
            var score = parseInt(it.score, 10);
            if (isNaN(score)) { score = 0; }

            var cls = score >= cfg.threshold ? 'ok' : 'ko';

            var volleys = parseInt(it.volleys, 10);
            var volleyText = '—';
            if (volleys > 0) {
                var volley = parseInt(it.volley, 10);
                if (isNaN(volley) || volley < 0) { volley = 0; }
                volleyText = volley + '/' + volleys;
            }

            rows += '<tr>'
                  + '<td class="c-ath">' + esc(it.athlete) + '</td>'
                  + '<td class="c-end">' + esc(volleyText) + '</td>'
                  + '<td class="c-score ' + cls + '">' + score + '</td>'
                  + '</tr>';
        });
        return '<section data-descr="' + esc(section.descr) + '">'
             + '<h2>' + esc(section.descr) + '</h2>'
             + '<table><tbody>' + rows + '</tbody></table>'
             + '</section>';
    }

    function render(sections) {
        if (!sections.length) {
            $scroller.innerHTML = '';
            if (!$main.querySelector('.empty')) {
                $main.insertAdjacentHTML('beforeend',
                    '<div class="empty">En attente des premiers scores…</div>');
            }
            $event.textContent = 'Flèches en direct';
            document.getElementById('summary').textContent = '';
            loopH = 0;
            return;
        }

        var empty = $main.querySelector('.empty');
        if (empty) { empty.remove(); }

        var body = sections.map(sectionHtml).join('');

        // Un seul exemplaire tant que le contenu tient à l'écran : le second
        // exemplaire ne sert qu'à boucler le défilement sans coupure.
        $scroller.innerHTML = '<div class="copy">' + body + '</div>';

        fitRows();
        measure();
        syncCopies();

        var total = sections.reduce(function (n, s) { return n + s.items.length; }, 0);
        document.getElementById('summary').textContent =
            sections.length + ' catégorie' + (sections.length > 1 ? 's' : '') +
            ' · ' + total + ' archer' + (total > 1 ? 's' : '');
    }

    function measure() {
        var copy = $scroller.querySelector('.copy');
        loopH = copy ? copy.offsetHeight : 0;

        anchors = [];
        var sections = copy ? copy.querySelectorAll('section') : [];
        for (var i = 0; i < sections.length; i++) {
            anchors.push({
                top:   sections[i].offsetTop,
                descr: sections[i].getAttribute('data-descr')
            });
        }
        if (loopH > 0 && offset >= loopH) {
            offset = offset % loopH;
        }
    }

    // Ajoute ou retire le second exemplaire selon que le contenu dépasse ou
    // non la hauteur visible. Appelé après chaque rendu et à chaque resize.
    function syncCopies() {
        var copy = $scroller.querySelector('.copy');
        if (!copy) { return; }

        var copies = $scroller.querySelectorAll('.copy');
        var scrollable = loopH > $main.clientHeight + 2;

        if (scrollable && copies.length < 2) {
            $scroller.insertAdjacentHTML('beforeend',
                '<div class="copy" aria-hidden="true">' + copy.innerHTML + '</div>');
        } else if (!scrollable && copies.length > 1) {
            for (var i = copies.length - 1; i >= 1; i--) {
                copies[i].parentNode.removeChild(copies[i]);
            }
        }
    }

    function currentDescr() {
        if (!anchors.length) { return ''; }
        var y = loopH > 0 ? (offset % loopH) : offset;
        var descr = anchors[anchors.length - 1].descr;
        for (var i = 0; i < anchors.length; i++) {
            if (anchors[i].top > y + 4) {
                descr = i > 0 ? anchors[i - 1].descr : anchors[0].descr;
                break;
            }
            if (i === anchors.length - 1) {
                descr = anchors[i].descr;
            }
        }
        return descr;
    }

    // setInterval plutôt que requestAnimationFrame : rAF est suspendu quand
    // la fenêtre passe à l'arrière-plan, ce qui figerait un écran laissé seul.
    function frame() {
        var now = (window.performance && performance.now) ? performance.now() : Date.now();
        var dt  = lastTs ? (now - lastTs) / 1000 : 0;
        lastTs  = now;

        if (dt > 1) { dt = 1; }

        var scrollable = loopH > $main.clientHeight + 2;

        if (!paused && scrollable && dt > 0) {
            offset += ($main.clientHeight / cfg.scrollsec) * dt;
            while (offset >= loopH) { offset -= loopH; }
            $scroller.style.transform = 'translateY(' + (-offset) + 'px)';
        } else if (!scrollable && offset !== 0) {
            offset = 0;
            $scroller.style.transform = 'translateY(0)';
        }

        var d = currentDescr();
        if (d && $event.textContent !== d) { $event.textContent = d; }
    }

    function armRefresh() {
        if (refreshTimer) { clearInterval(refreshTimer); }
        refreshTimer = setInterval(load, cfg.refresh * 1000);
    }

    function load() {
        var sep = DATA_URL.indexOf('?') >= 0 ? '&' : '?';
        fetch(DATA_URL + sep + '_=' + Date.now(), { cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (json.error) { throw new Error(json.error); }

                var oldRefresh = cfg.refresh;
                cfg.scrollsec = json.scrollsec || cfg.scrollsec;
                cfg.refresh   = json.refresh   || cfg.refresh;
                cfg.rows      = json.rows      || cfg.rows;
                if (typeof json.threshold !== 'undefined') {
                    cfg.threshold = parseInt(json.threshold, 10) || cfg.threshold;
                }
                if (cfg.refresh !== oldRefresh) { armRefresh(); }

                document.body.setAttribute('data-theme', json.theme || 'dark');
                document.getElementById('title').textContent = json.title || '';
                document.getElementById('demoBadge').hidden  = !json.demo;
                document.getElementById('legend').textContent =
                    'Seuil : ' + cfg.threshold + ' pts';

                render(json.sections || []);

                lastOk = Date.now();
                $dot.classList.remove('stale');
            })
            .catch(function () {
                $dot.classList.add('stale');
            });
    }

    function jump(step) {
        if (!anchors.length || !loopH) { return; }
        var y = offset % loopH;
        var i = 0;
        while (i < anchors.length - 1 && anchors[i + 1].top <= y + 4) { i++; }
        i = (i + step + anchors.length) % anchors.length;
        offset = anchors[i].top;
        $scroller.style.transform = 'translateY(' + (-offset) + 'px)';
    }

    setInterval(function () {
        tickClock();
        if (lastOk && Date.now() - lastOk > cfg.refresh * 3000) {
            $dot.classList.add('stale');
        }
    }, 1000);

    document.addEventListener('keydown', function (e) {
        if (e.key === ' ' || e.key === 'Spacebar' || e.code === 'Space') {
            paused = !paused;
            e.preventDefault();
        } else if (e.key === 'ArrowRight') {
            jump(1);
        } else if (e.key === 'ArrowLeft') {
            jump(-1);
        } else if (e.key === 'f' || e.key === 'F') {
            if (document.fullscreenElement) {
                document.exitFullscreen();
            } else if (document.documentElement.requestFullscreen) {
                document.documentElement.requestFullscreen().catch(function () {});
            }
        }
    });

    window.addEventListener('resize', function () {
        fitRows();
        measure();
        syncCopies();
    });

    tickClock();
    load();
    armRefresh();
    setInterval(frame, 33);   // ~30 images/s, suffisant pour un défilement lent
})();
</script>
</body>
</html>
