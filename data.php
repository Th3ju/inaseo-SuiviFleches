<?php
/**
 * Flèches en direct — source JSON interrogée en boucle par live.php.
 *
 * Lecture seule. Accepte ?tour=<ToId> pour fonctionner sur un poste sans
 * session IANSEO (vidéoprojecteur) : les scores affichés sont publics.
 *
 * ?demo=1 renvoie un jeu de données fictif, sans lire les scores réels.
 */

$suiviflBootstrap = dirname(dirname(__FILE__)) . '/config.php';
if (!is_file($suiviflBootstrap)) {
    $suiviflBootstrap = dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php';
}
require_once($suiviflBootstrap);
require_once('Common/Fun_Various.inc.php');
require_once(dirname(__FILE__) . '/Lib/Fun_SuiviFleches.php');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

$TourId = suivifl_TourId();
if (!$TourId) {
    http_response_code(404);
    echo json_encode(array('error' => 'Aucun concours sélectionné.'));
    exit;
}

$cfg  = suivifl_GetConfig($TourId);
$demo = !empty($_REQUEST['demo']);
$data = $demo ? suivifl_DemoData() : suivifl_Data($TourId, $cfg);

if (!empty($data['error'])) {
    http_response_code(500);
    echo json_encode(array('error' => $data['error']), JSON_UNESCAPED_UNICODE);
    exit;
}

$TourRs = safe_r_sql(
    "SELECT ToName FROM Tournament WHERE ToId=" . StrSafe_DB($TourId)
);
$Tour = $TourRs ? safe_fetch($TourRs) : false;

$title = $cfg['title'] !== ''
    ? $cfg['title']
    : ($Tour ? (string)$Tour->ToName : 'Flèches en direct');

echo json_encode(array(
    'error'      => 0,
    'demo'       => $demo ? 1 : 0,
    'serverTime' => time(),
    'title'      => $title,
    'threshold'  => (int)$cfg['threshold'],
    'refresh'    => (int)$cfg['refresh'],
    'rows'       => (int)$cfg['rows'],
    'scrollsec'  => (int)$cfg['scrollsec'],
    'sort'       => $cfg['sort'],
    'lastUpdate' => (int)$data['lastUpdate'],
    'sections'   => $data['sections'],
), JSON_UNESCAPED_UNICODE);
