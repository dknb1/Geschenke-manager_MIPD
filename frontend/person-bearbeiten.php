<?php
require_once __DIR__ . '/../backend/models/Person.php';
require_once __DIR__ . '/../backend/models/Anlass.php';
require_once __DIR__ . '/../backend/models/Geschenkidee.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    header('Location: person-anzeigen.php');
    exit;
}

$person = Person::finden($id);

if (!$person) {
    header('Location: person-anzeigen.php');
    exit;
}

$fehler = [];
$name = $person['name'];
$geburtsdatum = $person['geburtsdatum'];
$geschlecht = $person['geschlecht'] ?? '';
$details = $person['details'] ?? '';

$verknuepfteAnlaesse = array_merge(Anlass::geschuetzte(), Anlass::vonPerson($id));
usort(
    $verknuepfteAnlaesse,
    fn (array $a, array $b) => Anlass::naechstesVorkommen($a) <=> Anlass::naechstesVorkommen($b)
);

$geschenkideenDieserPerson = Geschenkidee::vonPerson($id);
$geschenkideenSortiert = Geschenkidee::sortiereAktuellUndVergangen($geschenkideenDieserPerson);
$aktuelleGeschenkideen = $geschenkideenSortiert['aktuell'];
$vergangeneGeschenke = $geschenkideenSortiert['vergangen'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aktion = $_POST['aktion'] ?? 'speichern';

    if ($aktion === 'loeschen') {
        Person::loeschen($id);
        header('Location: person-anzeigen.php');
        exit;
    }

    $name = trim($_POST['name'] ?? '');
    $geburtsdatum = trim($_POST['geburtsdatum'] ?? '');
    $geschlecht = $_POST['geschlecht'] ?? '';
    $details = trim($_POST['details'] ?? '');

    $fehler = Person::validiereEingabe($name, $geburtsdatum, $geschlecht, $details);

    if (empty($fehler)) {
        Person::aktualisieren(
            $id,
            $name,
            $geburtsdatum,
            $geschlecht !== '' ? $geschlecht : null,
            $details !== '' ? $details : null
        );
        header('Location: person-anzeigen.php');
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

    <?php include 'includes/navbar.php'; ?>

    <h1>Person verwalten</h1>

    <?php foreach ($fehler as $meldung): ?>
        <p class="fehler"><?= htmlspecialchars($meldung) ?></p>
    <?php endforeach; ?>

    <form method="post">

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

        <button type="submit" name="aktion" value="speichern">Änderungen speichern</button>
        <button type="submit" name="aktion" value="loeschen">Person löschen</button>

    </form>
    <h2>Anlässe von <?= htmlspecialchars($name) ?></h2>
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
            <a href="anlass-bearbeiten.php?id=<?= (int) $verknuepfterAnlass['id'] ?>">
                <?= htmlspecialchars($verknuepfterAnlass['name']) ?> -
                <?= htmlspecialchars(Anlass::naechstesVorkommen($verknuepfterAnlass)->format('d.m.Y')) ?>
                <?php if ((int) $verknuepfterAnlass['geschuetzt'] === 1): ?>
                    <strong>(Pflichtanlass)</strong>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>
    <h2>Aktuelle Geschenkideen für <?= htmlspecialchars($name) ?></h2>

    <?php if (empty($aktuelleGeschenkideen)): ?>
        <p>Keine aktuellen Geschenkideen hinterlegt.</p>
    <?php else: ?>
        <ul class="ideen-liste">
            <?php foreach ($aktuelleGeschenkideen as $idee): ?>
                <?php $anlassNamen = Geschenkidee::anlassNamenInklGeburtstag((int) $idee['id']); ?>
                <li>
                    <a href="idee-bearbeiten.php?id=<?= (int) $idee['id'] ?>">
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
    <h2>Vergangene Geschenke für <?= htmlspecialchars($name) ?></h2>
    <?php if (empty($vergangeneGeschenke)): ?>
        <p>Keine vergangenen Geschenke dokumentiert.</p>
    <?php else: ?>
        <ul class="ideen-liste">
            <?php foreach ($vergangeneGeschenke as $idee): ?>
                <?php $anlassNamen = Geschenkidee::anlassNamenInklGeburtstag((int) $idee['id']); ?>
                <li>
                    <a href="idee-bearbeiten.php?id=<?= (int) $idee['id'] ?>">
                        <?php if (!empty($idee['text'])): ?>
                            <?= htmlspecialchars($idee['text']) ?>
                        <?php endif; ?>

                        <?php if (!empty($anlassNamen)): ?>
                            (<?= htmlspecialchars(implode(', ', $anlassNamen)) ?>)
                        <?php endif; ?>
                    </a>

                    <?php if (!empty($idee['geschenk_datum'])): ?>
                        <p>
                            Geschenkt am:
                            <?= htmlspecialchars((new DateTimeImmutable($idee['geschenk_datum']))->format('d.m.Y')) ?>
                        </p>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <a href="person-anzeigen.php">Zurück zur Personenübersicht</a>

</body>

</html>