<?php
require_once __DIR__ . '/../backend/models/Anlass.php';
require_once __DIR__ . '/../backend/models/Person.php';
require_once __DIR__ . '/../backend/models/Ruecksprung.php';

$zurueck = Ruecksprung::ausAnfrage('anlaesse.php');

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    header('Location: ' . $zurueck);
    exit;
}

$anlass = Anlass::finden($id);

if (!$anlass) {
    header('Location: ' . $zurueck);
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
            header('Location: ' . $zurueck);
            exit;
        }
        $fehler[] = 'Dieser Anlass ist ein Pflichtanlass und kann nicht gelöscht werden.';
    } else {
        $istGeschuetzt = (int) $anlass['geschuetzt'] === 1;

        $name = trim($_POST['name'] ?? '');
        $datum = trim($_POST['datum'] ?? '');
        // Pflichtanlaesse haben kein Wiederholungs-Feld, der alte Wert bleibt.
        $wiederholung = $istGeschuetzt
            ? ($anlass['wiederholt_jaehrlich'] ? 'ja' : 'nein')
            : ($_POST['wiederholung'] ?? '');
        $gueltigePersonIds = array_column(Person::alle(), 'id');
        $personIds = array_values(array_intersect(
            array_map('intval', $_POST['person_ids'] ?? []),
            $gueltigePersonIds
        ));

        $fehler = Anlass::validiereNameUndDatum($name, $datum);

        if (!$istGeschuetzt && !in_array($wiederholung, ['ja', 'nein'], true)) {
            $fehler[] = 'Bitte angeben, ob sich der Anlass wiederholt.';
        }

        if (empty($fehler)) {
            Anlass::aktualisieren($id, $name, $datum, $wiederholung === 'ja', $personIds);
            header('Location: ' . $zurueck);
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
 <?php $navZurueck = $zurueck; include 'includes/navbar.php'; ?>
    <h1>Anlass bearbeiten</h1>

    <?php foreach ($fehler as $meldung): ?>
        <p class="fehler"><?= htmlspecialchars($meldung) ?></p>
    <?php endforeach; ?>

    <div class="abschnitte">

        <form method="post" class="ideen-formular">

            <h2>Angaben zum Anlass</h2>

            <input type="hidden" name="id" value="<?= (int) $id ?>">

            <label for="name">Name des Anlasses:</label>
            <input type="text" id="name" name="name" value="<?= htmlspecialchars($name) ?>" required>

            <label for="datum">Datum:</label>
            <input type="date" id="datum" name="datum" value="<?= htmlspecialchars($datum) ?>" required>

            <?php if ((int) $anlass['geschuetzt'] === 1): ?>
                <p class="hinweis-klein">Pflichtanlässe wie "<?= htmlspecialchars($anlass['name']) ?>" wiederholen sich fest
                    jährlich — das lässt sich nicht ändern.</p>
            <?php else: ?>
                <p class="feld-titel">Wiederholt sich der Anlass?</p>

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
                <p class="hinweis-klein">Pflichtanlässe wie "<?= htmlspecialchars($anlass['name']) ?>" betreffen alle Personen
                    gleichzeitig und können deshalb nicht einzelnen Personen zugeordnet werden.</p>
            <?php else: ?>
                <?php include 'includes/personen-auswahl.php'; ?>
            <?php endif; ?>

            <button type="submit" name="aktion" value="speichern">Änderungen speichern</button>

        </form>

        <?php /* Pflichtanlaesse koennen nicht geloescht werden, also gar kein Loesch-Kasten. */ ?>
        <?php if ((int) $anlass['geschuetzt'] !== 1): ?>
            <section class="kasten kasten-gefahr">

                <h2>Anlass löschen</h2>

                <p class="hinweis-klein">Entfernt den Anlass dauerhaft. Vorher kommt eine Sicherheitsabfrage.</p>

                <button type="button" popovertarget="anlass-loeschen-bestaetigen" class="gefahr-button">Anlass löschen</button>

            </section>
        <?php endif; ?>

    </div>

    <?php if ((int) $anlass['geschuetzt'] !== 1): ?>
        <div id="anlass-loeschen-bestaetigen" popover class="bestaetigungs-fenster">
            <div class="fenster-kopf">
                <h2>„<?= htmlspecialchars($anlass['name']) ?>“ wirklich löschen?</h2>
                <button class="schliessen"
                        popovertarget="anlass-loeschen-bestaetigen"
                        popovertargetaction="hide">
                    ×
                </button>
            </div>

            <p>Das kann nicht rückgängig gemacht werden.</p>

            <form method="post" class="fenster-buttons">
                <input type="hidden" name="id" value="<?= (int) $id ?>">
                <button type="button" popovertarget="anlass-loeschen-bestaetigen" popovertargetaction="hide">Abbrechen</button>
                <button type="submit" name="aktion" value="loeschen" class="gefahr-button">Ja, endgültig löschen</button>
            </form>
        </div>
    <?php endif; ?>

</body>

</html>
