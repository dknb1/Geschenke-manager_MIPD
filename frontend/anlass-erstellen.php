<?php
require_once __DIR__ . '/../backend/models/Anlass.php';
require_once __DIR__ . '/../backend/models/Person.php';
require_once __DIR__ . '/../backend/models/Ruecksprung.php';

// Von der Personenseite aus: Person vorauswaehlen und danach dorthin zurueck.
$standardZurueck = 'anlaesse.php';
$zurueck = Ruecksprung::ausAnfrage($standardZurueck);

$fehler = [];
$name = '';
$datum = '';
$wiederholung = '';
$personIds = [];

$vorausgewaehltePerson = Person::finden((int) filter_input(INPUT_GET, 'person', FILTER_VALIDATE_INT));
if ($vorausgewaehltePerson !== null) {
    $personIds = [(int) $vorausgewaehltePerson['id']];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $datum = trim($_POST['datum'] ?? '');
    $wiederholung = $_POST['wiederholung'] ?? '';
    $gueltigePersonIds = array_column(Person::alle(), 'id');
    $personIds = array_values(array_intersect(
        array_map('intval', $_POST['person_ids'] ?? []),
        $gueltigePersonIds
    ));

    $fehler = Anlass::validiereNameUndDatum($name, $datum);

    if (!in_array($wiederholung, ['ja', 'nein'], true)) {
        $fehler[] = 'Bitte angeben, ob sich der Anlass wiederholt.';
    }

    if (empty($fehler)) {
        Anlass::erstellen($name, $datum, $wiederholung === 'ja', $personIds);
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

    <title>Anlass erstellen</title>

    <link rel="stylesheet" href="css/style.css">
</head>

<body>
 <?php $navZurueck = $zurueck; include 'includes/navbar.php'; ?>
    <h1>Neuen Anlass erstellen</h1>

    <?php foreach ($fehler as $meldung): ?>
        <p class="fehler"><?= htmlspecialchars($meldung) ?></p>
    <?php endforeach; ?>

    <form method="post" class="ideen-formular">

        <label for="name">Name des Anlasses:</label>
        <input type="text" id="name" name="name" value="<?= htmlspecialchars($name) ?>" required>

        <label for="datum">Datum:</label>
        <input type="date" id="datum" name="datum" value="<?= htmlspecialchars($datum) ?>" required>

        <p class="feld-titel">Wiederholt sich der Anlass?</p>

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

</body>

</html>
