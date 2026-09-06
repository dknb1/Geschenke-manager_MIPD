<?php
require_once __DIR__ . '/../backend/models/Anlass.php';
require_once __DIR__ . '/../backend/models/Person.php';

$fehler = [];
$name = '';
$datum = '';
$wiederholung = '';
$personIds = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $datum = trim($_POST['datum'] ?? '');
    $wiederholung = $_POST['wiederholung'] ?? '';
    $gueltigePersonIds = array_column(Person::alle(), 'id');
    $personIds = array_values(array_intersect(
        array_map('intval', $_POST['person_ids'] ?? []),
        $gueltigePersonIds
    ));

    if ($name === '') {
        $fehler[] = 'Bitte einen Namen für den Anlass angeben.';
    }
    if ($datum === '' || !DateTime::createFromFormat('Y-m-d', $datum)) {
        $fehler[] = 'Bitte ein gültiges Datum angeben.';
    }
    if (!in_array($wiederholung, ['ja', 'nein'], true)) {
        $fehler[] = 'Bitte angeben, ob sich der Anlass wiederholt.';
    }

    if (empty($fehler)) {
        Anlass::erstellen($name, $datum, $wiederholung === 'ja', $personIds);
        header('Location: anlaesse.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Anlass erstellen</title>

    <link rel="stylesheet" href="css/style.css">
</head>

<body>
 <?php include 'includes/navbar.php'; ?>
    <h1>Neuen Anlass erstellen</h1>

    <?php foreach ($fehler as $meldung): ?>
        <p class="fehler"><?= htmlspecialchars($meldung) ?></p>
    <?php endforeach; ?>

    <form method="post">

        <label for="name">Name des Anlasses:</label>
        <input type="text" id="name" name="name" value="<?= htmlspecialchars($name) ?>" required>

        <label for="datum">Datum:</label>
        <input type="date" id="datum" name="datum" value="<?= htmlspecialchars($datum) ?>" required>

        <p>Wiederholt sich der Anlass?</p>

        <label>
            <input type="radio" name="wiederholung" value="ja" <?= $wiederholung === 'ja' ? 'checked' : '' ?>>
            Ja
        </label>

        <label>
            <input type="radio" name="wiederholung" value="nein" <?= $wiederholung === 'nein' ? 'checked' : '' ?>>
            Nein
        </label>

        <label for="person_ids">Personen (optional, Mehrfachauswahl möglich):</label>
        <select id="person_ids" name="person_ids[]" multiple size="8">
            <?php foreach (Person::alle() as $p): ?>
                <option value="<?= (int) $p['id'] ?>" <?= in_array((int) $p['id'], $personIds, true) ? 'selected' : '' ?>><?= htmlspecialchars($p['name']) ?></option>
            <?php endforeach; ?>
        </select>

        <button type="submit">Anlass erstellen</button>

    </form>

    <a href="anlaesse.php">Zurück zu meinen Anlässen</a>

</body>

</html>
