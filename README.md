# Flèches en direct — module IANSEO

Module d'affichage des scores d'un **passage de flèches** (ou de toute
qualification) sur un écran ou un vidéoprojecteur, mis à jour au fil de la
saisie des flèches.

- Entrée de menu : **Sorties → Flèches en direct**
- Archers regroupés par **division + classe**
- Chaque ligne : **nom, prénom et score** (rien d'autre)
- Score **rouge** sous le seuil, **vert à partir de 280** par défaut
- Les archers qui n'ont pas encore tiré sont masqués par défaut (option pour
  afficher tout le monde)
- Défilement continu si le contenu dépasse l'écran
- Aucune table à créer, aucun fichier du cœur d'IANSEO à modifier

## Installation

Copier ce dossier dans un sous-dossier de `Modules/Custom/` de l'installation
IANSEO :

```
<racine ianseo>/Modules/Custom/inaseo-SuiviFleches/
```

Le nom du dossier est libre : le module le déduit à l'exécution. Il est
conservé lors des mises à jour d'IANSEO, comme tout `Modules/Custom`.

L'entrée **Sorties → Flèches en direct** apparaît dès qu'un concours est
ouvert. Dans le code d'IANSEO, ce menu est la clé `MEDI` (« Output » en
anglais, « Sorties » en français).

## Utilisation

1. Ouvrir un concours dans IANSEO.
2. Menu **Sorties → Flèches en direct**.
3. Régler l'affichage, puis cliquer sur **Ouvrir l'affichage**.
4. Sur le poste relié au vidéoprojecteur, ouvrir l'URL affichée :

```
http://<ip-du-serveur>/Modules/Custom/inaseo-SuiviFleches/live.php?tour=123
```

L'écran fonctionne **sans session IANSEO** (poste dédié au vidéoprojecteur).
Ajouter `&demo=1` pour un jeu de données fictives, sans lire les scores réels.

Raccourcis clavier : **F** plein écran · **Espace** pause ·
**← →** catégorie précédente / suivante.

## Pourquoi ne pas utiliser `ianseo-scores-direct` ?

Le module [ianseo-scores-direct](https://github.com/rmzstrt/ianseo-scores-direct)
a été étudié comme point de départ, mais il ne répond pas au besoin d'un
passage de flèches :

| Point | `ianseo-scores-direct` | Ce module |
|---|---|---|
| Source des scores | `Individuals` via `Obj_Rank_Abs` | `Qualifications` (`QuD*Score`, `QuD*Hits`) |
| Dépend des `Events` | Oui (sections par épreuve) | Non |
| Dépend d'un classement calculé | Oui (`IndRank`) | Non |
| Lit les flèches saisies | Non | Oui (les colonnes `QuD*` sont mises à jour à chaque flèche) |
| Regroupement | Par épreuve | Par division + classe |
| Colonnes | rang, club, score, 10+, X | nom, prénom, score |

Sur un passage de flèches, il est fréquent qu'aucune épreuve (`Events`) ni
aucune ligne `Individuals` ne soit configurée : le module d'exemple n'a alors
rien à afficher. IANSEO met en revanche à jour `Qualifications` à chaque
flèche, y compris sans classement.

## Données lues

- `Qualifications.QuD1Score` … `QuD8Score` : score courant par distance
- `Qualifications.QuD1Hits` … `QuD8Hits` : flèches saisies
- `Qualifications.QuD1Arrowstring` … : chaîne des flèches (mise à jour par
  `Qualification/UpdateArrow.php` et `Qualification/UpdateQuals.php`)
- `Entries` : nom, prénom, division, classe, statut
- `Divisions` / `Classes` : libellés des catégories
- `Session` : nom des départs, si renseigné

Le module **ne lit pas** `Individuals` et **ne recalcule rien**. Il est donc à
jour dès qu'une flèche ou une volée est enregistrée.

## Réglages

Les réglages sont enregistrés par concours dans `ModulesParameters`, sous le
module `suivifl` (aucune table créée) :

| Réglage | Défaut | Rôle |
|---|---|---|
| `title` | vide | titre en haut à gauche ; vide = nom du concours |
| `threshold` | 280 | score à partir duquel le score passe au vert |
| `session` | 0 | 0 = tous les départs, sinon numéro de `QuSession` |
| `dist` | 0 | 0 = total, sinon n° de distance (1 à 8) |
| `sort` | `name` | `name` = ordre alphabétique, `score` = score décroissant |
| `onlystarted` | 1 | 1 = seulement ceux qui ont au moins une flèche ; 0 = tous |
| `rows` | 12 | lignes visibles (pilote la taille du texte) |
| `scrollsec` | 20 | secondes pour défiler une hauteur d'écran |
| `refresh` | 5 | secondes entre deux interrogations du serveur |

## Dépannage

**Aucune catégorie / aucun archer**

- Vérifier que les archers ont bien une ligne dans `Qualifications` (et pas
  seulement dans `Entries`).
- Vérifier `EnStatus <= 1` et `EnAthlete = 1`.
- Vérifier le départ sélectionné et la distance sélectionnée.
- Décocher « N'afficher que les archers ayant commencé » pour voir tout le
  monde, y compris les scores à 0.
- Les archers marqués DNS / DNF / DSQ restent affichés s'ils ont une ligne
  `Qualifications` ; sans flèche saisie, leur score est 0 (rouge) si vous
  décochez le filtre.

**Les libellés de division/classe s'affichent en code**

Renseigner les descriptions dans IANSEO
(**Compétition → Divisions et classes**) ou utiliser une clé préfixée par `~`
(`~Div-R`, `~Cl-S1H`).

**Le score ne change pas**

- Vérifier que la saisie met bien à jour `Qualifications` (saisie flèche par
  flèche, feuille de marque ou application de saisie).
- Augmenter la fréquence de rafraîchissement.
- Le voyant en haut à droite passe au rouge si `data.php` ne répond plus.

## Sécurité

`live.php` et `data.php` sont **publics par conception** : l'écran doit
pouvoir tourner sur un poste sans session IANSEO, et les scores affichés sont
déjà publics. `index.php` (réglages) exige l'accès **Sorties** (`AclOutput`).

## Compatibilité

Testé avec IANSEO 2025-02-10 (rev 29), PHP 8.2 et MariaDB 11. Les colonnes
`QuD1…QuD8` utilisées sont celles du schéma standard d'IANSEO.
