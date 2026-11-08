<?php
/**
 * Flèches en direct — page de pilotage.
 *
 * Réglages de l'affichage, lancement de l'écran et aperçu du nombre d'archers
 * réellement affichés. Les scores sont lus dans Qualifications ; aucun
 * classement ni aucune épreuve ne doit être configuré dans IANSEO.
 */

$suiviflBootstrap = dirname(dirname(__FILE__)) . '/config.php';
if (!is_file($suiviflBootstrap)) {
    $suiviflBootstrap = dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php';
}
require_once($suiviflBootstrap);
require_once('Common/Fun_Various.inc.php');
require_once(dirname(__FILE__) . '/Lib/Fun_SuiviFleches.php');

CheckTourSession(true);
checkACL(AclOutput, AclReadOnly);

$TourId = (int)$_SESSION['TourId'];
$Saved  = false;

if (!empty($_POST['save'])) {
    checkACL(AclOutput, AclReadWrite);

    suivifl_SaveConfig($TourId, array(
        'title'       => isset($_POST['title']) ? $_POST['title'] : '',
        'threshold'   => isset($_POST['threshold']) ? $_POST['threshold'] : 280,
        'refresh'     => isset($_POST['refresh']) ? $_POST['refresh'] : 5,
        'rows'        => isset($_POST['rows']) ? $_POST['rows'] : 12,
        'scrollsec'   => isset($_POST['scrollsec']) ? $_POST['scrollsec'] : 20,
        'dist'        => isset($_POST['dist']) ? $_POST['dist'] : 0,
        'session'     => isset($_POST['session']) ? $_POST['session'] : 0,
        'onlystarted' => !empty($_POST['onlystarted']) ? 1 : 0,
        'sort'        => isset($_POST['sort']) ? $_POST['sort'] : 'name',
    ));

    $Saved = true;
}

$cfg      = suivifl_GetConfig($TourId);
$Sessions = suivifl_SessionList($TourId);
$Preview  = suivifl_Data($TourId, $cfg);

$TourRs = safe_r_sql(
    "SELECT ToName, ToNumDist FROM Tournament WHERE ToId=" . StrSafe_DB($TourId)
);
$Tour = $TourRs ? safe_fetch($TourRs) : false;
$TourName = $Tour ? (string)$Tour->ToName : '';
$NumDist  = $Tour ? max(1, (int)$Tour->ToNumDist) : 1;

$ArcherCountRs = safe_r_sql(
    "SELECT COUNT(*) AS Nb
       FROM Entries
      WHERE EnTournament = " . StrSafe_DB($TourId) . "
        AND EnAthlete = 1
        AND EnStatus <= 1"
);
$ArcherCount = ($ArcherCountRs && ($ArcherCountRow = safe_fetch($ArcherCountRs)))
    ? (int)$ArcherCountRow->Nb
    : 0;

$PreviewSections = count($Preview['sections']);
$PreviewArchers  = 0;
foreach ($Preview['sections'] as $section) {
    $PreviewArchers += count($section['items']);
}

$liveUrl = $CFG->ROOT_DIR . 'Modules/Custom/' . basename(dirname(__FILE__))
         . '/live.php?tour=' . $TourId;

$scheme = 'http';
if ((!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
    || (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)) {
    $scheme = 'https';
}
$absUrl = $scheme . '://' . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '') . $liveUrl;

$PAGE_TITLE = 'Flèches en direct';
include('Common/Templates/head.php');
?>

<h1>Flèches en direct</h1>

<?php if ($Saved) { ?>
<p style="padding:.5em;background:#dff0d8;border:1px solid #b2d8a7;">
    <i class="fa fa-check"></i> Réglages enregistrés.
</p>
<?php } ?>

<?php if (!empty($Preview['error'])) { ?>
<p style="padding:.5em;background:#f2dede;border:1px solid #e0b4b4;">
    <i class="fa fa-times"></i> <?php echo htmlspecialchars($Preview['error']); ?>
</p>
<?php } ?>

<div style="display:flex;align-items:center;gap:1.5em;margin:.8em 0;flex-wrap:wrap;">
    <a class="Link" href="<?php echo htmlspecialchars($liveUrl); ?>" target="_blank" rel="noopener">
        <i class="fa fa-tv"></i> <b>Ouvrir l'affichage</b>
    </a>
    <a class="Link" href="<?php echo htmlspecialchars($liveUrl); ?>&amp;demo=1" target="_blank" rel="noopener">
        <i class="fa fa-flask"></i> Aperçu avec des données fictives
    </a>
    <span style="color:#555;">
        <?php echo (int)$PreviewArchers; ?> archer(s) dans
        <?php echo (int)$PreviewSections; ?> catégorie(s) avec les réglages actuels.
    </span>
</div>

<p style="color:#555;">
    Sur le poste relié au vidéoprojecteur, ouvrez&nbsp;:
    <code><?php echo htmlspecialchars($absUrl); ?></code><br>
    L'écran fonctionne sans session IANSEO. Touche <b>F</b> pour le plein écran,
    <b>Espace</b> pour figer le défilement, <b>&larr; &rarr;</b> pour changer de catégorie.
</p>

<div style="padding:.7em 1em;background:#eef5ff;border-left:4px solid #4a90d9;margin:1em 0;">
    <b><i class="fa fa-info-circle"></i> Mode de lecture</b><br>
    Le module lit directement les scores et les chaînes de flèches de la table
    <code>Qualifications</code>. Il ne dépend ni des <code>Events</code>, ni de la table
    <code>Individuals</code>, ni d'un recalcul de classement : il fonctionne donc pour un
    passage de flèches, même si aucune épreuve n'est configurée.
</div>

<?php if ($ArcherCount === 0) { ?>
<div style="padding:.7em;background:#fcf8e3;border-left:4px solid #f0ad4e;margin-bottom:1em;">
    <b><i class="fa fa-exclamation-triangle"></i> Aucun archer inscrit trouvé.</b><br>
    Vérifiez que les archers sont bien enregistrés dans <code>Entries</code>
    (statut valide). Ils apparaîtront même sans score, avec 0 point.
</div>
<?php } ?>

<form method="post" action="index.php">
<input type="hidden" name="save" value="1">

<table class="Tabella">
    <tr class="Titolo"><th colspan="2">Réglages de l'affichage</th></tr>

    <tr class="Modifica">
        <td style="width:38%;">Titre affiché en haut à gauche</td>
        <td>
            <input type="text" name="title" size="50" maxlength="80"
                   value="<?php echo htmlspecialchars($cfg['title']); ?>"
                   placeholder="<?php echo htmlspecialchars($TourName); ?>">
            <span style="color:#777;">(vide = nom du concours)</span>
        </td>
    </tr>

    <tr>
        <td>Départ affiché</td>
        <td>
            <select name="session">
                <option value="0"<?php echo $cfg['session'] == 0 ? ' selected' : ''; ?>>Tous les départs</option>
<?php foreach ($Sessions as $session) {
    // QuSession = 0 est déjà couvert par « Tous les départs ».
    if ((int)$session['id'] === 0) { continue; }
?>
                <option value="<?php echo (int)$session['id']; ?>"<?php echo $cfg['session'] == $session['id'] ? ' selected' : ''; ?>>
                    <?php echo htmlspecialchars($session['label']); ?>
                    (<?php echo (int)$session['nb']; ?> archer<?php echo $session['nb'] > 1 ? 's' : ''; ?>)
                </option>
<?php } ?>
            </select>
        </td>
    </tr>

    <tr class="Modifica">
        <td>Score affiché</td>
        <td>
            <select name="dist">
                <option value="0"<?php echo $cfg['dist'] == 0 ? ' selected' : ''; ?>>Total du concours</option>
<?php for ($d = 1; $d <= $NumDist; $d++) { ?>
                <option value="<?php echo $d; ?>"<?php echo $cfg['dist'] == $d ? ' selected' : ''; ?>>
                    Distance / série <?php echo $d; ?>
                </option>
<?php } ?>
            </select>
            <span style="color:#777;">(pour un passage de flèches sur une seule distance, le total et la distance 1 sont identiques)</span>
        </td>
    </tr>

    <tr>
        <td>Seuil du vert</td>
        <td>
            <input type="number" name="threshold" min="1" max="1000"
                   value="<?php echo (int)$cfg['threshold']; ?>">
            <span style="color:#777;">points — score rouge en dessous, vert à partir de ce seuil (défaut : 280)</span>
        </td>
    </tr>

    <tr class="Modifica">
        <td>Ordre dans les catégories</td>
        <td>
            <select name="sort">
                <option value="name"<?php echo $cfg['sort'] === 'name' ? ' selected' : ''; ?>>Nom de l'archer</option>
                <option value="score"<?php echo $cfg['sort'] === 'score' ? ' selected' : ''; ?>>Score décroissant</option>
            </select>
        </td>
    </tr>

    <tr>
        <td>Archers affichés</td>
        <td>
            <label>
                <input type="checkbox" name="onlystarted" value="1"<?php echo $cfg['onlystarted'] ? ' checked' : ''; ?>>
                N'afficher que les archers ayant commencé (au moins une flèche)
            </label>
            <span style="color:#777;">(décoché : tous les archers, avec 0 point et 0/6 avant leur première flèche)</span>
        </td>
    </tr>

    <tr class="Modifica">
        <td>Lignes visibles à l'écran</td>
        <td>
            <input type="number" name="rows" min="4" max="40" value="<?php echo (int)$cfg['rows']; ?>">
            <span style="color:#777;">(pilote la taille du texte : moins de lignes = plus gros)</span>
        </td>
    </tr>

    <tr>
        <td>Vitesse de défilement</td>
        <td>
            <input type="number" name="scrollsec" min="3" max="120" value="<?php echo (int)$cfg['scrollsec']; ?>">
            <span style="color:#777;">secondes pour faire défiler une hauteur d'écran — plus grand = plus lent</span>
        </td>
    </tr>

    <tr class="Modifica">
        <td>Rafraîchissement des scores (secondes)</td>
        <td>
            <input type="number" name="refresh" min="2" max="300" value="<?php echo (int)$cfg['refresh']; ?>">
            <span style="color:#777;">(2 à 300 secondes)</span>
        </td>
    </tr>
</table>

<br>
<div align="center"><input type="submit" value="Enregistrer les réglages"></div>
</form>

<br>
<h2>Fonctionnement</h2>
<p style="max-width:900px;">
    Les archers sont regroupés par division et classe (par exemple
    « Arc Classique — Sénior 1 Homme »). Chaque ligne affiche le nom, le prénom,
    la volée courante (par exemple <code>4/6</code>) puis le score courant.
    Le score est rouge sous le seuil et vert à partir du seuil. Tous les archers
    inscrits sont affichés, même sans score : ils apparaissent avec 0 point et
    <code>0/6</code>. Le départ affiché peut être « Tous les départs » ou un
    seul départ.
</p>
<p style="max-width:900px;color:#555;">
    IANSEO met à jour <code>QuD1Score</code>, <code>QuD1Hits</code> et
    <code>QuD1Arrowstring</code> à chaque flèche saisie
    (<code>Qualification/UpdateArrow.php</code>) ou à chaque feuille de marque
    enregistrée (<code>Qualification/UpdateQuals.php</code>). Le nombre total de
    volées et de flèches par volée vient de <code>DistanceInformation</code>.
    L'écran se rafraîchit donc tout seul, sans aucun recalcul.
</p>

<?php include('Common/Templates/tail.php'); ?>
