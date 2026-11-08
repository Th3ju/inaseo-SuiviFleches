<?php
/**
 * Flèches en direct — entrée de menu.
 *
 * Ce fichier est inclus dans Common/Menu.php sur toutes les pages : il doit
 * rester minimal (aucune requête, aucun appel de fonction risqué).
 *
 * $on  = true si un concours est ouvert
 * $ret = tableau des menus ; MEDI correspond au menu « Sorties »
 *       (clé « Media / Output » d'IANSEO, libellée « Sorties » en français).
 */

if ($on) {
    $suiviflDir = basename(dirname(__FILE__));

    $ret['MEDI'][] = MENU_DIVIDER;
    $ret['MEDI'][] = 'Flèches en direct' . '|' . $CFG->ROOT_DIR . 'Modules/Custom/' . $suiviflDir . '/index.php';
}
