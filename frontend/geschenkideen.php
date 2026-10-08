<?php
require_once __DIR__ . '/../backend/models/Geschenkidee.php';
require_once __DIR__ . '/../backend/models/GeschenkideeFilter.php';
require_once __DIR__ . '/../backend/models/Person.php';
require_once __DIR__ . '/../backend/models/Ruecksprung.php';

$filter = GeschenkideeFilter::ausAnfrage($_GET);
$filterAktiv = GeschenkideeFilter::istAktiv($filter);
$rubriken = GeschenkideeFilter::anwenden($filter);
$anzahl = array_sum(array_map('count', $rubriken));

// Ruecksprungziel fuer die Links, damit der Filter nach dem Bearbeiten erhalten bleibt.
$hierher = GeschenkideeFilter::alsUrl($filter);
$zurueck = Ruecksprung::ausAnfrage('index.php');
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Meine Geschenkideen</title>

    <link rel="stylesheet" href="css/style.css">
</head>

<body>

    <?php $navZurueck = $zurueck; include 'includes/navbar.php'; ?>

    <h1>Meine Geschenkideen</h1>

    <a href="<?= htmlspecialchars(Ruecksprung::anhaengen('idee-speichern.php', $hierher)) ?>" class="aktions-button">Neue Geschenkidee erstellen</a>
    <?php /* Generieren hat keinen eigenen Platz auf der Startseite, damit jede Karte zwei Buttons hat. */ ?>
    <a href="<?= htmlspecialchars(Ruecksprung::anhaengen('ideen-generieren.php', $hierher)) ?>" class="aktions-button">Geschenkideen generieren</a>

    <?php /* GET wie bei "Meine Anlaesse"; "filter=1" zeigt, dass abgesendet wurde. */ ?>
    <form method="get" class="anlass-filter kasten">
        <input type="hidden" name="filter" value="1">
        <input type="hidden" name="zurueck" value="<?= htmlspecialchars($zurueck) ?>">

        <div class="anlass-filter-felder">
            <label>
                Suche
                <input type="search" name="suche" maxlength="100"
                       value="<?= htmlspecialchars($filter['suche']) ?>"
                       placeholder="Idee oder Person">
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
                Besorgt
                <select name="besorgt">
                    <option value="" <?= $filter['besorgt'] === null ? 'selected' : '' ?>>Egal</option>
                    <option value="1" <?= $filter['besorgt'] === true ? 'selected' : '' ?>>Bereits besorgt</option>
                    <option value="0" <?= $filter['besorgt'] === false ? 'selected' : '' ?>>Noch nicht besorgt</option>
                </select>
            </label>
        </div>

        <div class="anlass-filter-schalter">
            <?php foreach (GeschenkideeFilter::STATUS as $status => $bezeichnung): ?>
                <label>
                    <input type="checkbox" name="<?= $status ?>" value="1" <?= in_array($status, $filter['status'], true) ? 'checked' : '' ?>>
                    <?= htmlspecialchars($bezeichnung) ?>
                </label>
            <?php endforeach; ?>
        </div>

        <div class="anlass-filter-aktionen">
            <button type="submit">Filtern</button>
            <?php if ($filterAktiv): ?>
                <a href="<?= htmlspecialchars(Ruecksprung::anhaengen('geschenkideen.php', $zurueck)) ?>">Filter zurücksetzen</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($filterAktiv): ?>
        <p class="anlass-filter-treffer"><?= $anzahl ?> <?= $anzahl === 1 ? 'Eintrag gefunden' : 'Einträge gefunden' ?></p>
    <?php endif; ?>

    <?php foreach ($rubriken as $status => $ideen): ?>
        <section class="kasten">
            <h2><?= htmlspecialchars(GeschenkideeFilter::STATUS[$status]) ?> (<?= count($ideen) ?>)</h2>

            <?php if (empty($ideen)): ?>
                <p><?= $filterAktiv ? 'Nichts passt zu diesem Filter.' : 'Keine Einträge vorhanden.' ?></p>
            <?php else: ?>
                <ul class="ideen-liste">
                    <?php foreach ($ideen as $idee): ?>
                        <?php $anlassNamen = Geschenkidee::anlassNamenInklGeburtstag((int) $idee['id']); ?>
                        <li>
                            <a href="<?= htmlspecialchars(Ruecksprung::anhaengen('idee-bearbeiten.php?id=' . (int) $idee['id'], $hierher)) ?>">
                                <?php /* Ideen nur mit Link oder Bild brauchen trotzdem etwas zum Anklicken. */ ?>
                                <?= !empty($idee['text']) ? htmlspecialchars($idee['text']) : 'Idee ohne Text' ?>
                                <?php if (!empty($anlassNamen)): ?>
                                    (<?= htmlspecialchars(implode(', ', $anlassNamen)) ?>)
                                <?php endif; ?>
                            </a>
                            <p>Für: <strong><?= htmlspecialchars($idee['person_name']) ?></strong></p>
                            <?php if ($status !== 'vergangen'): ?>
                                <p>Besorgt: <strong><?= (int) ($idee['besorgt'] ?? 0) === 1 ? 'Ja' : 'Nein' ?></strong></p>
                                <?php if (!empty($idee['offene_aufgaben'])): ?>
                                    <p>Offene Aufgaben: <?= htmlspecialchars($idee['offene_aufgaben']) ?></p>
                                <?php endif; ?>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>

</body>

</html>
