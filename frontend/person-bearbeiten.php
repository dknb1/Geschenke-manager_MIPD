<?php
require_once __DIR__ . '/../backend/models/Person.php';

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
$alter = $person['alter'] !== null ? (string) $person['alter'] : '';
$geschlecht = $person['geschlecht'] ?? '';
$details = $person['details'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aktion = $_POST['aktion'] ?? 'speichern';

    if ($aktion === 'loeschen') {
        Person::loeschen($id);
        header('Location: person-anzeigen.php');
        exit;
    }

    $name = trim($_POST['name'] ?? '');
    $alter = trim($_POST['alter'] ?? '');
    $geschlecht = $_POST['geschlecht'] ?? '';
    $details = trim($_POST['details'] ?? '');

    if ($name === '') {
        $fehler[] = 'Bitte einen Namen angeben.';
    } elseif (!Person::istGueltigerName($name)) {
        $fehler[] = 'Der Name darf nur Buchstaben, Leerzeichen, Bindestriche und Apostrophe enthalten.';
    }

    if (!Person::istGueltigesAlter($alter)) {
        $fehler[] = 'Bitte ein gültiges Alter zwischen 0 und 120 angeben.';
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
            $alter !== '' ? (int) $alter : null,
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

    <h1>Person verwalten</h1>

    <?php foreach ($fehler as $meldung): ?>
        <p class="fehler"><?= htmlspecialchars($meldung) ?></p>
    <?php endforeach; ?>

    <form method="post">

        <input type="hidden" name="id" value="<?= (int) $id ?>">

        <label for="name">Name:</label>
        <input type="text" id="name" name="name" value="<?= htmlspecialchars($name) ?>" maxlength="100" required>

        <label for="alter">Alter:</label>
        <input type="number" id="alter" name="alter" value="<?= htmlspecialchars($alter) ?>" min="0" max="120">

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

    <a href="person-anzeigen.php">Zurück zur Personenübersicht</a>

</body>

</html>
