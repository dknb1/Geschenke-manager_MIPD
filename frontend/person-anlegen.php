<?php
require_once __DIR__ . '/../backend/models/Person.php';

$fehler = [];
$name = '';
$alter = '';
$geschlecht = '';
$details = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        Person::erstellen(
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

    <title>Person anlegen</title>

    <link rel="stylesheet" href="css/style.css">
</head>

<body>

    <h1>Person anlegen</h1>

    <?php foreach ($fehler as $meldung): ?>
        <p class="fehler"><?= htmlspecialchars($meldung) ?></p>
    <?php endforeach; ?>

    <form method="post">

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

        <button type="submit">Person speichern</button>

    </form>

    <a href="person-anzeigen.php">Zurück zur Personenübersicht</a>

    <a href="index.html">Zurück zur Startseite</a>

</body>

</html>
