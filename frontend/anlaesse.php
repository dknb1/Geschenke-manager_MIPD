<?php
require_once __DIR__ . '/../backend/models/Anlass.php';
require_once __DIR__ . '/../backend/models/AnlassFilter.php';
require_once __DIR__ . '/../backend/models/Person.php';
require_once __DIR__ . '/../backend/models/Ruecksprung.php';

// Enthaelt auch die Geburtstage der Personen.
$filter = AnlassFilter::ausAnfrage($_GET);
$filterAktiv = AnlassFilter::istAktiv($filter);
$anlaesse = AnlassFilter::anwenden($filter);
$monate = AnlassFilter::nachMonatGruppiert($anlaesse);

// Ruecksprungziel fuer die Links, damit der Filter nach dem Bearbeiten erhalten bleibt.
$hierher = AnlassFilter::alsUrl($filter);
$zurueck = Ruecksprung::ausAnfrage('index.php');
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Meine Anlässe</title>

    <link rel="stylesheet" href="css/style.css">
</head>

<body>

 <?php $navZurueck = $zurueck; include 'includes/navbar.php'; ?>

    <h1>Meine Anlässe</h1>

    <a href="<?= htmlspecialchars(Ruecksprung::anhaengen('anlass-erstellen.php', $hierher)) ?>" class="aktions-button">Neuen Anlass erstellen</a>

    <?php /* GET, damit der Filter in der URL steht. "filter=1" zeigt, dass abgesendet wurde
             (abgewaehlte Haekchen fehlen sonst einfach). */ ?>
    <form method="get" class="anlass-filter kasten">
        <input type="hidden" name="filter" value="1">
        <input type="hidden" name="zurueck" value="<?= htmlspecialchars($zurueck) ?>">

        <div class="anlass-filter-felder">
            <label>
                Suche
                <input type="search" name="suche" maxlength="100"
                       value="<?= htmlspecialchars($filter['suche']) ?>"
                       placeholder="Anlass oder Person">
            </label>

            <label>
                Person
                <select name="person">
                    <option value="">Alle Personen</option>
                    <?php foreach (Person::alle() as $p): ?>
                        <option value="<?= (int) $p['id'] ?>" <?= $filter['person'] === (int) $p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                Zeitraum
                <select name="zeitraum">
                    <?php foreach (AnlassFilter::ZEITRAEUME as $tage => $bezeichnung): ?>
                        <option value="<?= $tage ?>" <?= $filter['zeitraum'] === $tage ? 'selected' : '' ?>><?= htmlspecialchars($bezeichnung) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>

        <div class="anlass-filter-schalter">
            <?php foreach (AnlassFilter::ARTEN as $art => $bezeichnung): ?>
                <label>
                    <input type="checkbox" name="<?= $art ?>" value="1" <?= in_array($art, $filter['arten'], true) ? 'checked' : '' ?>>
                    <?= htmlspecialchars($bezeichnung) ?>
                </label>
            <?php endforeach; ?>
            <label>
                <input type="checkbox" name="vergangene" value="1" <?= $filter['vergangene'] ? 'checked' : '' ?>>
                Vergangene einmalige Anlässe
            </label>
        </div>

        <div class="anlass-filter-aktionen">
            <button type="submit">Filtern</button>
            <?php if ($filterAktiv): ?>
                <a href="<?= htmlspecialchars(Ruecksprung::anhaengen('anlaesse.php', $zurueck)) ?>">Filter zurücksetzen</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($filterAktiv): ?>
        <p class="anlass-filter-treffer"><?= count($anlaesse) ?> <?= count($anlaesse) === 1 ? 'Anlass gefunden' : 'Anlässe gefunden' ?></p>
    <?php endif; ?>

    <?php if (empty($anlaesse)): ?>
        <p><?= $filterAktiv ? 'Keine Anlässe passen zu diesem Filter.' : 'Es wurden noch keine Anlässe angelegt.' ?></p>
    <?php else: ?>
        <?php foreach ($monate as $monat => $anlaesseImMonat): ?>
            <h2><?= htmlspecialchars($monat) ?></h2>

            <?php foreach ($anlaesseImMonat as $anlass): ?>
                <?php
                $link = $anlass['ist_geburtstag']
                    ? 'person-bearbeiten.php?id=' . (int) $anlass['person_id']
                    : 'anlass-bearbeiten.php?id=' . (int) $anlass['id'];
                ?>
                <a href="<?= htmlspecialchars(Ruecksprung::anhaengen($link, $hierher)) ?>" class="anlass-eintrag">
                    <?= htmlspecialchars($anlass['name']) ?> - <?= htmlspecialchars($anlass['naechstes_vorkommen']->format('d.m.Y')) ?><?php if (!$anlass['ist_geburtstag'] && !empty($anlass['personen'])): ?> (<?= htmlspecialchars(implode(', ', $anlass['personen'])) ?>)<?php endif; ?>
                    <?php if ($anlass['ist_geburtstag']): ?>
                        <strong>(Geburtstag)</strong>
                    <?php elseif ((int) $anlass['geschuetzt'] === 1): ?>
                        <strong>(Pflichtanlass)</strong>
                    <?php endif; ?>
                    <?php if ($anlass['vergangen']): ?>
                        <strong>(vergangen)</strong>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    <?php endif; ?>

</body>

</html>
