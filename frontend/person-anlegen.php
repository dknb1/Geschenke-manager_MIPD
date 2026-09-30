<?php
require_once __DIR__ . '/../backend/models/Person.php';
require_once __DIR__ . '/../backend/models/Interesse.php';

$fehler = [];
$name = '';
$geburtsdatum = '';
$geschlecht = '';
$details = '';
$interessen = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $geburtsdatum = trim($_POST['geburtsdatum'] ?? '');
    $geschlecht = $_POST['geschlecht'] ?? '';
    $details = trim($_POST['details'] ?? '');
    $interessen = Interesse::nurGueltige((array) ($_POST['interessen'] ?? []));

    $fehler = Person::validiereEingabe($name, $geburtsdatum, $geschlecht, $details);

    if (empty($fehler)) {
        $personId = Person::erstellen(
            $name,
            $geburtsdatum,
            $geschlecht !== '' ? $geschlecht : null,
            $details !== '' ? $details : null
        );
        Interesse::fuerPersonSetzen($personId, $interessen);
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
 <?php $navZurueck = 'person-anzeigen.php'; include 'includes/navbar.php'; ?>

    <h1>Person anlegen</h1>

    <?php foreach ($fehler as $meldung): ?>
        <p class="fehler"><?= htmlspecialchars($meldung) ?></p>
    <?php endforeach; ?>

    <form method="post">

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

        <button type="submit">Person speichern</button>

    </form>

    <a href="person-anzeigen.php">Zurück zur Personenübersicht</a>

</body>

</html>
