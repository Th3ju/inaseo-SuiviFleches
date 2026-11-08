<?php
/**
 * Flèches en direct — fonctions communes.
 *
 * Le module lit directement la table Qualifications d'IANSEO :
 *   - QuD1Score … QuD8Score  : score courant de chaque distance
 *   - QuD1Hits  … QuD8Hits   : nombre de flèches saisies
 *   - QuD1Arrowstring …       : chaîne des flèches (non utilisée pour l'affichage,
 *                               mais c'est elle qu'IANSEO met à jour à chaque flèche)
 *
 * Il ne dépend ni de la table Individuals, ni des Events, ni du moteur de
 * classement : c'est ce qui permet de l'utiliser pour un passage de flèches
 * (6 volées de 6 flèches, 360 points max, 280 points pour la validation).
 *
 * Ce fichier suppose que config.php a déjà été inclus par la page appelante.
 */

define('SUIVIFL_MODULE', 'suivifl');
define('SUIVIFL_DEFAULT_THRESHOLD', 280);

/**
 * Convertit une valeur de configuration en entier, avec repli sur le défaut.
 */
function suivifl_Int($value, $default)
{
    return is_numeric($value) ? (int)$value : (int)$default;
}

/**
 * Valeurs par défaut de la configuration.
 * Les clés sont limitées à 30 caractères (MpParameter).
 */
function suivifl_Defaults()
{
    return array(
        'title'       => '',   // titre libre ; vide = nom du concours
        'threshold'   => 280,  // score à partir duquel le score passe au vert
        'refresh'     => 5,    // secondes entre deux interrogations du serveur
        'rows'        => 12,   // lignes visibles (pilote la taille du texte)
        'scrollsec'   => 20,   // secondes pour défiler une hauteur d'écran
        'dist'        => 0,    // 0 = total, sinon n° de distance
        'session'     => 0,    // 0 = tous les départs
        'onlystarted' => 1,    // 1 = uniquement les archers ayant au moins une flèche
        'sort'        => 'name', // 'name' ou 'score'
    );
}

/**
 * Détermine le concours à afficher.
 * Accepte ?tour=<ToId> pour fonctionner sur un poste sans session IANSEO
 * (vidéoprojecteur), sinon reprend le concours ouvert en session.
 *
 * @return int 0 si aucun concours valide
 */
function suivifl_TourId()
{
    $TourId = 0;

    if (isset($_REQUEST['tour']) && ctype_digit((string)$_REQUEST['tour'])) {
        $TourId = (int)$_REQUEST['tour'];
    } elseif (!empty($_SESSION['TourId'])) {
        $TourId = (int)$_SESSION['TourId'];
    }

    if (!$TourId) {
        return 0;
    }

    $Rs = safe_r_sql("SELECT ToId FROM Tournament WHERE ToId=" . StrSafe_DB($TourId));
    return ($Rs && safe_num_rows($Rs)) ? $TourId : 0;
}

/**
 * Lit la configuration du module pour un concours, complétée par les défauts.
 */
function suivifl_GetConfig($TourId)
{
    $cfg = suivifl_Defaults();

    foreach ($cfg as $key => $default) {
        $value = getModuleParameter(SUIVIFL_MODULE, $key, $default, $TourId);
        $cfg[$key] = is_scalar($value) ? $value : $default;
    }

    $cfg['title']       = trim((string)$cfg['title']);
    $cfg['threshold']   = max(1, min(1000, suivifl_Int($cfg['threshold'], SUIVIFL_DEFAULT_THRESHOLD)));
    $cfg['refresh']     = max(2, min(300, suivifl_Int($cfg['refresh'], 5)));
    $cfg['rows']        = max(4, min(40, suivifl_Int($cfg['rows'], 12)));
    $cfg['scrollsec']   = max(3, min(120, suivifl_Int($cfg['scrollsec'], 20)));
    $cfg['dist']        = max(0, min(8, suivifl_Int($cfg['dist'], 0)));
    $cfg['session']     = max(0, suivifl_Int($cfg['session'], 0));
    $cfg['onlystarted'] = !empty($cfg['onlystarted']) ? 1 : 0;
    $cfg['sort']        = in_array($cfg['sort'], array('name', 'score'), true)
        ? $cfg['sort']
        : 'name';

    return $cfg;
}

/**
 * Enregistre la configuration du module.
 */
function suivifl_SaveConfig($TourId, array $cfg)
{
    $clean = array(
        'title'       => (isset($cfg['title']) && is_scalar($cfg['title'])) ? trim((string)$cfg['title']) : '',
        'threshold'   => max(1, min(1000, suivifl_Int(isset($cfg['threshold']) ? $cfg['threshold'] : null, SUIVIFL_DEFAULT_THRESHOLD))),
        'refresh'     => max(2, min(300, suivifl_Int(isset($cfg['refresh']) ? $cfg['refresh'] : null, 5))),
        'rows'        => max(4, min(40, suivifl_Int(isset($cfg['rows']) ? $cfg['rows'] : null, 12))),
        'scrollsec'   => max(3, min(120, suivifl_Int(isset($cfg['scrollsec']) ? $cfg['scrollsec'] : null, 20))),
        'dist'        => max(0, min(8, suivifl_Int(isset($cfg['dist']) ? $cfg['dist'] : null, 0))),
        'session'     => max(0, suivifl_Int(isset($cfg['session']) ? $cfg['session'] : null, 0)),
        'onlystarted' => !empty($cfg['onlystarted']) ? 1 : 0,
        'sort'        => (isset($cfg['sort']) && $cfg['sort'] === 'score') ? 'score' : 'name',
    );

    foreach ($clean as $key => $value) {
        setModuleParameter(SUIVIFL_MODULE, $key, $value, $TourId);
    }
}

/**
 * Traduit une description stockée dans Divisions/Classes.
 *
 * IANSEO accepte deux formes :
 *   - le texte déjà localisé (« Arc Classique ») : on le renvoie tel quel ;
 *   - une clé préfixée par « ~ » (~Div-R) : on la traduit, en français par
 *     défaut, pour que l'affichage reste lisible même sans session IANSEO.
 */
function suivifl_Label($text)
{
    $text = trim((string)$text);
    if ($text === '') {
        return '';
    }

    if ($text[0] === '~') {
        $key = substr($text, 1);

        $tmp = get_text($key, 'Tournament', '', false, false, 'fr');
        if (strpos($tmp, '[[') === false) {
            return $tmp;
        }

        $tmp = get_text($key, 'Common', '', false, false, 'fr');
        if (strpos($tmp, '[[') === false) {
            return $tmp;
        }

        return $key;
    }

    return get_text($text, '', '', true);
}

/**
 * Liste des départs (QuSession) qui ont au moins un archer qualifié.
 *
 * @return array id => array('id','label','nb')
 */
function suivifl_SessionList($TourId)
{
    $out = array();

    $Rs = safe_r_sql(
        "SELECT q.QuSession AS SessionId,
                MAX(NULLIF(s.SesName, '')) AS SesName,
                COUNT(DISTINCT q.QuId) AS Nb
           FROM Qualifications AS q
           INNER JOIN Entries AS e
                   ON e.EnId = q.QuId
                  AND e.EnTournament = " . StrSafe_DB($TourId) . "
           LEFT JOIN Session AS s
                  ON s.SesTournament = e.EnTournament
                 AND s.SesOrder = q.QuSession
                 AND s.SesType = 'Q'
          WHERE e.EnTournament = " . StrSafe_DB($TourId) . "
            AND e.EnAthlete = 1
            AND e.EnStatus <= 1
          GROUP BY q.QuSession
          ORDER BY q.QuSession"
    );

    if (!$Rs) {
        return $out;
    }

    while ($row = safe_fetch($Rs)) {
        $id   = (int)$row->SessionId;
        $name = trim((string)$row->SesName);

        if ($name !== '') {
            $label = $name . ' — Départ ' . $id;
        } elseif ($id > 0) {
            $label = 'Départ ' . $id;
        } else {
            $label = 'Sans départ';
        }

        $out[$id] = array(
            'id'    => $id,
            'label' => $label,
            'nb'    => (int)$row->Nb,
        );
    }

    return $out;
}

/**
 * Données affichées : archers regroupés par division + classe.
 *
 * Le score provient de Qualifications. Pour un passage de flèches configuré
 * sur une seule distance, le total et la distance 1 donnent le même résultat ;
 * le réglage « distance » permet de choisir explicitement l'un ou l'autre.
 *
 * @return array array('sections' => array(...), 'lastUpdate' => int, 'error' => string)
 */
function suivifl_Data($TourId, array $cfg)
{
    $dist = (int)$cfg['dist'];

    $scoreExpr = $dist > 0
        ? "q.QuD{$dist}Score"
        : "(q.QuD1Score+q.QuD2Score+q.QuD3Score+q.QuD4Score+q.QuD5Score+q.QuD6Score+q.QuD7Score+q.QuD8Score)";
    $hitsExpr = $dist > 0
        ? "q.QuD{$dist}Hits"
        : "(q.QuD1Hits+q.QuD2Hits+q.QuD3Hits+q.QuD4Hits+q.QuD5Hits+q.QuD6Hits+q.QuD7Hits+q.QuD8Hits)";

    $where = "e.EnTournament = " . StrSafe_DB($TourId) . "
              AND e.EnAthlete = 1
              AND e.EnStatus <= 1";

    if ((int)$cfg['session'] > 0) {
        $where .= " AND q.QuSession = " . (int)$cfg['session'];
    }
    if (!empty($cfg['onlystarted'])) {
        $where .= " AND {$hitsExpr} > 0";
    }

    $Sql = "SELECT e.EnId,
                   e.EnName,
                   e.EnFirstName,
                   e.EnDivision,
                   e.EnClass,
                   q.QuSession,
                   q.QuTimestamp,
                   {$scoreExpr} AS Score,
                   {$hitsExpr} AS Hits,
                   COALESCE(NULLIF(d.DivDescription, ''), e.EnDivision) AS DivDescription,
                   COALESCE(NULLIF(c.ClDescription, ''), e.EnClass) AS ClDescription,
                   COALESCE(d.DivViewOrder, 999) AS DivOrder,
                   COALESCE(c.ClViewOrder, 999) AS ClOrder
              FROM Qualifications AS q
              INNER JOIN Entries AS e
                      ON e.EnId = q.QuId
                     AND e.EnTournament = " . StrSafe_DB($TourId) . "
              LEFT JOIN Divisions AS d
                     ON d.DivTournament = e.EnTournament
                    AND d.DivId = e.EnDivision
              LEFT JOIN Classes AS c
                     ON c.ClTournament = e.EnTournament
                    AND c.ClId = e.EnClass
             WHERE {$where}
             ORDER BY e.EnName, e.EnFirstName";

    $Rs = safe_r_sql($Sql);
    if (!$Rs) {
        return array(
            'sections'   => array(),
            'lastUpdate' => 0,
            'error'      => 'Erreur de lecture de la table Qualifications.',
        );
    }

    $sections   = array();
    $lastUpdate = 0;

    while ($row = safe_fetch($Rs)) {
        if (!empty($cfg['onlystarted']) && (int)$row->Hits <= 0) {
            continue;
        }

        $last = trim((string)$row->EnName);
        $first = trim((string)$row->EnFirstName);
        $name = trim($last . ' ' . $first);
        if ($name === '') {
            continue;
        }

        $ts = !empty($row->QuTimestamp) ? strtotime($row->QuTimestamp) : 0;
        if ($ts && $ts > $lastUpdate) {
            $lastUpdate = $ts;
        }

        $divCode = trim((string)$row->EnDivision);
        $clCode  = trim((string)$row->EnClass);
        $key     = $divCode . '|' . $clCode;

        if (!isset($sections[$key])) {
            $divDescr = suivifl_Label($row->DivDescription);
            $clDescr  = suivifl_Label($row->ClDescription);

            if ($divDescr === '') {
                $divDescr = $divCode;
            }
            if ($clDescr === '') {
                $clDescr = $clCode;
            }

            if ($divDescr !== '' && $clDescr !== '') {
                $descr = $divDescr . ' — ' . $clDescr;
            } elseif ($divDescr !== '') {
                $descr = $divDescr;
            } elseif ($clDescr !== '') {
                $descr = $clDescr;
            } else {
                $descr = 'Sans catégorie';
            }

            $sections[$key] = array(
                'division' => $divCode,
                'class'    => $clCode,
                'descr'    => $descr,
                'items'    => array(),
                '_do'      => (int)$row->DivOrder,
                '_co'      => (int)$row->ClOrder,
            );
        }

        $sections[$key]['items'][] = array(
            'athlete' => $name,
            'score'   => (int)$row->Score,
            'hits'    => (int)$row->Hits,
        );
    }

    // Ordre des archers dans chaque catégorie.
    foreach ($sections as &$section) {
        if ($cfg['sort'] === 'score') {
            usort($section['items'], function ($a, $b) {
                if ($a['score'] !== $b['score']) {
                    return $b['score'] <=> $a['score'];
                }
                return strcasecmp($a['athlete'], $b['athlete']);
            });
        } else {
            usort($section['items'], function ($a, $b) {
                $cmp = strcasecmp($a['athlete'], $b['athlete']);
                if ($cmp !== 0) {
                    return $cmp;
                }
                return $b['score'] <=> $a['score'];
            });
        }
    }
    unset($section);

    // Ordre des catégories : ordre d'affichage IANSEO, puis libellé.
    uasort($sections, function ($a, $b) {
        if ($a['_do'] !== $b['_do']) {
            return $a['_do'] <=> $b['_do'];
        }
        if ($a['_co'] !== $b['_co']) {
            return $a['_co'] <=> $b['_co'];
        }
        return strcasecmp($a['descr'], $b['descr']);
    });

    foreach ($sections as &$section) {
        unset($section['_do'], $section['_co']);
    }
    unset($section);

    return array(
        'sections'   => array_values($sections),
        'lastUpdate' => (int)$lastUpdate,
        'error'      => '',
    );
}

/**
 * Jeu de données fictif pour régler l'écran avant le concours.
 * N'interroge pas la base et n'y écrit rien.
 */
function suivifl_DemoData()
{
    $data = array(
        'Arc Classique — Sénior 1 Homme' => array(
            array('MARTIN Julien', 300),
            array('BERNARD Camille', 292),
            array('THOMAS Lucas', 285),
            array('PETIT Emma', 281),
            array('ROBERT Hugo', 279),
            array('RICHARD Léa', 270),
            array('DURAND Nathan', 265),
            array('DUBOIS Chloé', 250),
        ),
        'Arc Classique — Sénior 1 Femme' => array(
            array('MOREAU Manon', 296),
            array('LAURENT Inès', 288),
            array('SIMON Jade', 282),
            array('MICHEL Lina', 278),
            array('LEFEBVRE Léna', 268),
            array('GARCIA Ana', 255),
        ),
        'Arc à Poulies — Sénior 2 Homme' => array(
            array('DAVID Raphaël', 320),
            array('PETITOT Marc', 312),
            array('ROUSSEAU Alain', 305),
            array('VINCENT Paul', 298),
            array('FOURNIER Eric', 290),
        ),
        'Arc Nu — Vétéran Homme' => array(
            array('GARNIER Jean', 275),
            array('MARCHAND Louis', 260),
            array('OLIVIER Pierre', 245),
        ),
    );

    $sections = array();
    foreach ($data as $descr => $archers) {
        $items = array();
        foreach ($archers as $archer) {
            $items[] = array(
                'athlete' => $archer[0],
                'score'   => max(0, $archer[1] + mt_rand(-1, 1)),
                'hits'    => 36,
            );
        }
        $sections[] = array(
            'division' => '',
            'class'    => '',
            'descr'    => $descr,
            'items'    => $items,
        );
    }

    return array(
        'sections'   => $sections,
        'lastUpdate' => time(),
        'error'      => '',
    );
}
