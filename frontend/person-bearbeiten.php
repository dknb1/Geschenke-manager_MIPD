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
$verknuepfteAnlaesse = Anlass::vonPerson($id);
$geschenkideenDieserPerson = Geschenkidee::vonPerson($id);

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

    if ($name === '') {
        $fehler[] = 'Bitte einen Namen angeben.';
    } elseif (!Person::istGueltigerName($name)) {
        $fehler[] = 'Der Name darf nur Buchstaben, Leerzeichen, Bindestriche und Apostrophe enthalten.';
    }

    if ($geburtsdatum === '') {
        $fehler[] = 'Bitte ein Geburtsdatum angeben.';
    } elseif (!Person::istGueltigesGeburtsdatum($geburtsdatum)) {
        $fehler[] = 'Bitte ein gültiges Geburtsdatum angeben (nicht in der Zukunft).';
    }

    if (!Person::istGueltigesGeschlecht($geschlecht)) {
        $fehler[] = 'Bitte ein gültiges Geschlecht auswählen.';
    }

    if (!Person::istGueltigeDetails($details)) {
        $fehler[] = 'Die Details enthalten nicht erlaubte Inhalte oder sind zu lang (max. 1000 Zeichen).';
    }

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
        <?= htmlspecialchars($geburtstagAlsAnlass['name']) ?> - <?= htmlspecialchars(Anlass::naechstesVorkommen($geburtstagAlsAnlass)->format('d.m.Y')) ?>
        <strong>(Geburtstag)</strong>
    </p>

    <?php if (empty($verknuepfteAnlaesse)): ?>
        <p>Keine weiteren Anlässe hinterlegt.</p>
    <?php else: ?>
        <?php foreach ($verknuepfteAnlaesse as $verknuepfterAnlass): ?>
            <a href="anlass-bearbeiten.php?id=<?= (int) $verknuepfterAnlass['id'] ?>">
                <?= htmlspecialchars($verknuepfterAnlass['name']) ?> - <?= htmlspecialchars(Anlass::naechstesVorkommen($verknuepfterAnlass)->format('d.m.Y')) ?>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>

    <h2>Geschenkideen für <?= htmlspecialchars($name) ?></h2>

    <?php if (empty($geschenkideenDieserPerson)): ?>
        <p>Keine Geschenkideen hinterlegt.</p>
    <?php else: ?>
        <ul class="ideen-liste">
            <?php foreach ($geschenkideenDieserPerson as $idee): ?>
                <li>
                    <?php if (!empty($idee['text'])): ?>
                        <?= htmlspecialchars($idee['text']) ?>
                    <?php endif; ?>
                    <?php if (!empty($idee['link'])): ?>
                        <a href="<?= htmlspecialchars($idee['link']) ?>" target="_blank" rel="noopener noreferrer">Link</a>
                    <?php endif; ?>
                    <?php if (!empty($idee['bild_link'])): ?>
                        <a href="<?= htmlspecialchars($idee['bild_link']) ?>" target="_blank" rel="noopener noreferrer">Bild</a>
                    <?php endif; ?>
                    <a href="idee-bearbeiten.php?id=<?= (int) $idee['id'] ?>">Bearbeiten</a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <a href="person-anzeigen.php">Zurück zur Personenübersicht</a>

</body>

</html>
