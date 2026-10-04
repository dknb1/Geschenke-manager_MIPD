<?php
require_once __DIR__ . '/../backend/models/Person.php';
require_once __DIR__ . '/../backend/models/Anlass.php';
require_once __DIR__ . '/../backend/models/Geschenkidee.php';
require_once __DIR__ . '/../backend/models/Interesse.php';
require_once __DIR__ . '/../backend/models/Ruecksprung.php';

$zurueck = Ruecksprung::ausAnfrage('person-anzeigen.php');

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    header('Location: ' . $zurueck);
    exit;
}

$person = Person::finden($id);

if (!$person) {
    header('Location: ' . $zurueck);
    exit;
}

$fehler = [];
$name = $person['name'];
$geburtsdatum = $person['geburtsdatum'];
$geschlecht = $person['geschlecht'] ?? '';
$details = $person['details'] ?? '';
$interessen = Interesse::vonPerson($id);

$verknuepfteAnlaesse = Anlass::vonPersonInklGeschuetzte($id);

$geschenkideenDieserPerson = Geschenkidee::vonPerson($id);
$geschenkideenSortiert = Geschenkidee::sortiereNachStatus($geschenkideenDieserPerson);
$offeneGeschenkideen = $geschenkideenSortiert['offen'];
$festeGeschenke = $geschenkideenSortiert['fest'];
$vergangeneGeschenke = $geschenkideenSortiert['vergangen'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aktion = $_POST['aktion'] ?? 'speichern';

    if ($aktion === 'loeschen') {
        Person::loeschen($id);
        header('Location: ' . $zurueck);
        exit;
    }

    if ($aktion === 'share_link_erstellen') {
        Person::shareTokenGenerieren($id);
        header('Location: ' . Ruecksprung::anhaengen('person-bearbeiten.php?id=' . $id, $zurueck));
        exit;
    }

    $name = trim($_POST['name'] ?? '');
    $geburtsdatum = trim($_POST['geburtsdatum'] ?? '');
    $geschlecht = $_POST['geschlecht'] ?? '';
    $details = trim($_POST['details'] ?? '');
    $interessen = Interesse::nurGueltige((array) ($_POST['interessen'] ?? []));

    $fehler = Person::validiereEingabe($name, $geburtsdatum, $geschlecht, $details, $id);

    if (empty($fehler)) {
        Person::aktualisieren(
            $id,
            $name,
            $geburtsdatum,
            $geschlecht !== '' ? $geschlecht : null,
            $details !== '' ? $details : null
        );
        Interesse::fuerPersonSetzen($id, $interessen);
        header('Location: ' . $zurueck);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Person verwalten</title>

    <link rel="stylesheet" href="css/style.css">
</head>

<body>

    <?php $navZurueck = $zurueck; include 'includes/navbar.php'; ?>

    <h1>Person verwalten</h1>

    <?php foreach ($fehler as $meldung): ?>
        <p class="fehler"><?= htmlspecialchars($meldung) ?></p>
    <?php endforeach; ?>

    <div class="abschnitte">

        <form method="post" class="ideen-formular">

            <h2>Angaben zur Person</h2>

            <input type="hidden" name="id" value="<?= (int) $id ?>">

            <label for="name">Name:</label>
            <input type="text" id="name" name="name" value="<?= htmlspecialchars($name) ?>" maxlength="100" required>

            <label for="geburtsdatum">Geburtsdatum:</label>
            <input type="date" id="geburtsdatum" name="geburtsdatum" value="<?= htmlspecialchars($geburtsdatum) ?>" required>

            <label for="geschlecht">Geschlecht:</label>
            <select id="geschlecht" name="geschlecht">
                <option value="">Bitte auswählen</option>
                <option value="maennlich" <?= $geschlecht === 'maennlich' ? 'selected' : '' ?>>Männlich</option>
                <option value="weiblich" <?= $geschlecht === 'weiblich' ? 'selected' : '' ?>>Weiblich</option>
                <option value="divers" <?= $geschlecht === 'divers' ? 'selected' : '' ?>>Divers</option>
            </select>

            <label for="details">Details:</label>
            <textarea id="details" name="details" rows="5" maxlength="1000"><?= htmlspecialchars($details) ?></textarea>

            <?php include 'includes/interessen-auswahl.php'; ?>

            <button type="submit" name="aktion" value="speichern">Änderungen speichern</button>

        </form>

        <section class="kasten">
            <h2>Anlässe von <?= htmlspecialchars($name) ?></h2>

            <a href="<?= htmlspecialchars(Ruecksprung::anhaengen('anlass-erstellen.php?person=' . $id, 'person-bearbeiten.php?id=' . $id)) ?>" class="aktions-button">Neuen Anlass für <?= htmlspecialchars($name) ?> erstellen</a>
            <?php $geburtstagAlsAnlass = Person::geburtstagAlsAnlass($person); ?>
            <p>
                <?= htmlspecialchars($geburtstagAlsAnlass['name']) ?> -
                <?= htmlspecialchars(Anlass::naechstesVorkommen($geburtstagAlsAnlass)->format('d.m.Y')) ?>
                <strong>(Geburtstag)</strong>
            </p>
            <?php if (empty($verknuepfteAnlaesse)): ?>
                <p>Keine weiteren Anlässe hinterlegt.</p>
            <?php else: ?>
                <?php foreach ($verknuepfteAnlaesse as $verknuepfterAnlass): ?>
                    <a href="<?= htmlspecialchars(Ruecksprung::anhaengen('anlass-bearbeiten.php?id=' . (int) $verknuepfterAnlass['id'], 'person-bearbeiten.php?id=' . $id)) ?>">
                        <?= htmlspecialchars($verknuepfterAnlass['name']) ?> -
                        <?= htmlspecialchars(Anlass::naechstesVorkommen($verknuepfterAnlass)->format('d.m.Y')) ?>
                        <?php if ((int) $verknuepfterAnlass['geschuetzt'] === 1): ?>
                            <strong>(Pflichtanlass)</strong>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <section class="kasten">
            <h2>Geschenkideen für <?= htmlspecialchars($name) ?></h2>

            <a href="<?= htmlspecialchars(Ruecksprung::anhaengen('idee-speichern.php?person=' . $id, 'person-bearbeiten.php?id=' . $id)) ?>" class="aktions-button">Neue Geschenkidee für <?= htmlspecialchars($name) ?> anlegen</a>
            <a href="ideen-generieren.php?person=<?= $id ?>" class="aktions-button">Geschenkideen generieren lassen</a>

            <?php if (empty($offeneGeschenkideen)): ?>
                <p>Keine offenen Geschenkideen hinterlegt.</p>
            <?php else: ?>
                <ul class="ideen-liste">
                    <?php foreach ($offeneGeschenkideen as $idee): ?>
                        <?php $anlassNamen = Geschenkidee::anlassNamenInklGeburtstag((int) $idee['id']); ?>
                        <li>
                            <a href="<?= htmlspecialchars(Ruecksprung::anhaengen('idee-bearbeiten.php?id=' . (int) $idee['id'], 'person-bearbeiten.php?id=' . $id)) ?>">
                                <?php if (!empty($idee['text'])): ?>
                                    <?= htmlspecialchars($idee['text']) ?>
                                <?php endif; ?>
                                <?php if (!empty($anlassNamen)): ?>
                                    (<?= htmlspecialchars(implode(', ', $anlassNamen)) ?>)
                                <?php endif; ?>
                            </a>
                            <p>Besorgt: <strong><?= (int) ($idee['besorgt'] ?? 0) === 1 ? 'Ja' : 'Nein' ?></strong></p>
                            <?php if (!empty($idee['offene_aufgaben'])): ?>
                                <p>Offene Aufgaben: <?= htmlspecialchars($idee['offene_aufgaben']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($idee['link']) || !empty($idee['bild_link'])): ?>
                                <div class="ideen-liste-extras">
                                    <?php if (!empty($idee['link'])): ?>
                                        <a href="<?= htmlspecialchars($idee['link']) ?>" target="_blank" rel="noopener noreferrer">Link</a>
                                    <?php endif; ?>

                                    <?php if (!empty($idee['bild_link'])): ?>
                                        <a href="<?= htmlspecialchars($idee['bild_link']) ?>" target="_blank" rel="noopener noreferrer">Bild</a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="kasten">
            <h2>Festgelegte Geschenke für <?= htmlspecialchars($name) ?></h2>
            <?php if (empty($festeGeschenke)): ?>
                <p>Keine Geschenke fest zugeordnet.</p>
            <?php else: ?>
                <ul class="ideen-liste">
                    <?php foreach ($festeGeschenke as $idee): ?>
                        <?php $anlassNamen = Geschenkidee::anlassNamenInklGeburtstag((int) $idee['id']); ?>
                        <li>
                            <a href="<?= htmlspecialchars(Ruecksprung::anhaengen('idee-bearbeiten.php?id=' . (int) $idee['id'], 'person-bearbeiten.php?id=' . $id)) ?>">
                                <?php if (!empty($idee['text'])): ?>
                                    <?= htmlspecialchars($idee['text']) ?>
                                <?php endif; ?>
                                <?php if (!empty($anlassNamen)): ?>
                                    (<?= htmlspecialchars(implode(', ', $anlassNamen)) ?>)
                                <?php endif; ?>
                            </a>
                            <p>Besorgt: <strong><?= (int) ($idee['besorgt'] ?? 0) === 1 ? 'Ja' : 'Nein' ?></strong></p>
                            <?php if (!empty($idee['offene_aufgaben'])): ?>
                                <p>Offene Aufgaben: <?= htmlspecialchars($idee['offene_aufgaben']) ?></p>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="kasten">
            <h2>Vergangene Geschenke für <?= htmlspecialchars($name) ?></h2>
            <?php if (empty($vergangeneGeschenke)): ?>
                <p>Keine vergangenen Geschenke dokumentiert.</p>
            <?php else: ?>
                <ul class="ideen-liste">
                    <?php foreach ($vergangeneGeschenke as $idee): ?>
                        <?php $anlassNamen = Geschenkidee::anlassNamenInklGeburtstag((int) $idee['id']); ?>
                        <li>
                            <a href="<?= htmlspecialchars(Ruecksprung::anhaengen('idee-bearbeiten.php?id=' . (int) $idee['id'], 'person-bearbeiten.php?id=' . $id)) ?>">
                                <?php if (!empty($idee['text'])): ?>
                                    <?= htmlspecialchars($idee['text']) ?>
                                <?php endif; ?>

                                <?php if (!empty($anlassNamen)): ?>
                                    (<?= htmlspecialchars(implode(', ', $anlassNamen)) ?>)
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

        </section>

        <section class="kasten">
            <h2>Geschenke und Ideen für <?= htmlspecialchars($name) ?> teilen</h2>

            <?php if (empty($person['share_token'])): ?>

                <p>Noch kein Link erstellt. Der Link zeigt eine schlanke, eigenständige Seite mit den
                    offenen Ideen und festgelegten Geschenken dieser Person (ohne Login, ohne Zugriff auf
                    den Rest der Anwendung) - z. B. zum Verschicken an Familie oder Freunde.</p>

                <form method="post">
                    <input type="hidden" name="id" value="<?= (int) $id ?>">
                    <button type="submit" name="aktion" value="share_link_erstellen">Link zum Teilen erstellen</button>
                </form>

            <?php else: ?>

                <?php
                    $shareSchema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $shareBasispfad = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
                    $shareUrl = $shareSchema . '://' . $_SERVER['HTTP_HOST'] . $shareBasispfad
                        . '/share.php?token=' . urlencode($person['share_token']);
                ?>

                <label for="share_link">Link zum Teilen (Text markieren und kopieren):</label>
                <input type="text" id="share_link" value="<?= htmlspecialchars($shareUrl) ?>" readonly>

                <form method="post">
                    <input type="hidden" name="id" value="<?= (int) $id ?>">
                    <button type="submit" name="aktion" value="share_link_erstellen">Link neu generieren (alter Link wird ungültig)</button>
                </form>

            <?php endif; ?>

        </section>

        <section class="kasten kasten-gefahr">
            <h2>Person löschen</h2>

            <p class="hinweis-klein">Entfernt die Person mit allen ihren Geschenken und Ideen dauerhaft. Vorher kommt eine Sicherheitsabfrage.</p>

            <?php /* Erst nach Bestaetigung, weil alle Geschenke und Ideen der Person mitgeloescht werden. */ ?>
            <button type="button" popovertarget="person-loeschen-bestaetigen" class="gefahr-button">Person löschen</button>
        </section>

    </div>

    <div id="person-loeschen-bestaetigen" popover class="bestaetigungs-fenster">
        <div class="fenster-kopf">
            <h2><?= htmlspecialchars($person['name']) ?> wirklich löschen?</h2>
            <button class="schliessen"
                    popovertarget="person-loeschen-bestaetigen"
                    popovertargetaction="hide">
                ×
            </button>
        </div>

        <p>
            <?php if (count($geschenkideenDieserPerson) > 0): ?>
                Dabei werden auch <strong><?= count($geschenkideenDieserPerson) ?>
                <?= count($geschenkideenDieserPerson) === 1 ? 'Geschenk bzw. Idee' : 'Geschenke und Ideen' ?></strong>
                dieser Person gelöscht.
            <?php endif; ?>
            <?php if (!empty($person['share_token'])): ?>
                Der Link zum Teilen wird ungültig.
            <?php endif; ?>
            Das kann nicht rückgängig gemacht werden.
        </p>

        <form method="post" class="fenster-buttons">
            <input type="hidden" name="id" value="<?= (int) $id ?>">
            <button type="button" popovertarget="person-loeschen-bestaetigen" popovertargetaction="hide">Abbrechen</button>
            <button type="submit" name="aktion" value="loeschen" class="gefahr-button">Ja, endgültig löschen</button>
        </form>
    </div>

</body>

</html>