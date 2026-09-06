<?php
require_once __DIR__ . '/../backend/models/Anlass.php';
require_once __DIR__ . '/../backend/models/Person.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    header('Location: anlaesse.php');
    exit;
}

$anlass = Anlass::finden($id);

if (!$anlass) {
    header('Location: anlaesse.php');
    exit;
}

$fehler = [];
$name = $anlass['name'];
$datum = $anlass['datum'];
$wiederholung = $anlass['wiederholt_jaehrlich'] ? 'ja' : 'nein';
$personIds = array_map('intval', array_column(Anlass::personen($id), 'id'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aktion = $_POST['aktion'] ?? 'speichern';

    if ($aktion === 'loeschen') {
        if (Anlass::loeschen($id)) {
            header('Location: anlaesse.php');
            exit;
        }
        $fehler[] = 'Dieser Anlass ist ein Pflichtanlass und kann nicht gelöscht werden.';
    } else {
        $istGeschuetzt = (int) $anlass['geschuetzt'] === 1;

        $name = trim($_POST['name'] ?? '');
        $datum = trim($_POST['datum'] ?? '');
        // Bei geschuetzten Anlaessen gibt es kein Wiederholung-Feld im Formular (siehe unten) -
        // der bestehende Wert bleibt unangetastet, statt einen Pflichtfeldfehler auszuloesen.
        $wiederholung = $istGeschuetzt
            ? ($anlass['wiederholt_jaehrlich'] ? 'ja' : 'nein')
            : ($_POST['wiederholung'] ?? '');
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
        if (!$istGeschuetzt && !in_array($wiederholung, ['ja', 'nein'], true)) {
            $fehler[] = 'Bitte angeben, ob sich der Anlass wiederholt.';
        }

        if (empty($fehler)) {
            Anlass::aktualisieren($id, $name, $datum, $wiederholung === 'ja', $personIds);
            header('Location: anlaesse.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Anlass bearbeiten</title>

    <link rel="stylesheet" href="css/style.css">
</head>

<body>
 <?php include 'includes/navbar.php'; ?>
    <h1>Anlass bearbeiten</h1>

    <?php foreach ($fehler as $meldung): ?>
        <p class="fehler"><?= htmlspecialchars($meldung) ?></p>
    <?php endforeach; ?>

    <form method="post">

        <input type="hidden" name="id" value="<?= (int) $id ?>">

        <label for="name">Name des Anlasses:</label>
        <input type="text" id="name" name="name" value="<?= htmlspecialchars($name) ?>" required>

        <label for="datum">Datum:</label>
        <input type="date" id="datum" name="datum" value="<?= htmlspecialchars($datum) ?>" required>

        <?php if ((int) $anlass['geschuetzt'] === 1): ?>
            <p>Pflichtanlässe wie "<?= htmlspecialchars($anlass['name']) ?>" wiederholen sich fest
                jährlich — das lässt sich nicht ändern.</p>
        <?php else: ?>
            <p>Wiederholt sich der Anlass?</p>

            <label>
                <input type="radio" name="wiederholung" value="ja" <?= $wiederholung === 'ja' ? 'checked' : '' ?>>
                Ja
            </label>

            <label>
                <input type="radio" name="wiederholung" value="nein" <?= $wiederholung === 'nein' ? 'checked' : '' ?>>
                Nein
            </label>
        <?php endif; ?>

        <?php if ((int) $anlass['geschuetzt'] === 1): ?>
            <p>Pflichtanlässe wie "<?= htmlspecialchars($anlass['name']) ?>" betreffen alle Personen
                gleichzeitig und können deshalb nicht einzelnen Personen zugeordnet werden.</p>
        <?php else: ?>
            <label for="person_ids">Personen (optional, Mehrfachauswahl möglich):</label>
            <select id="person_ids" name="person_ids[]" multiple size="8">
                <?php foreach (Person::alle() as $p): ?>
                    <option value="<?= (int) $p['id'] ?>" <?= in_array((int) $p['id'], $personIds, true) ? 'selected' : '' ?>><?= htmlspecialchars($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>

        <button type="submit" name="aktion" value="speichern">Änderungen speichern</button>

        <button type="submit" name="aktion" value="loeschen">Anlass löschen</button>

    </form>

    <a href="anlaesse.php">Zurück zu meinen Anlässen</a>

</body>

</html>
