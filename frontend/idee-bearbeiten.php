<?php
require_once __DIR__ . '/../backend/models/Person.php';
require_once __DIR__ . '/../backend/models/Anlass.php';
require_once __DIR__ . '/../backend/models/Geschenkidee.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    header('Location: idee-speichern.php');
    exit;
}

$idee = Geschenkidee::finden($id);

if (!$idee) {
    header('Location: idee-speichern.php');
    exit;
}

$fehler = [];
$personId = (string) $idee['person_id'];
$text = $idee['text'] ?? '';
$link = $idee['link'] ?? '';
$bildLink = $idee['bild_link'] ?? '';
$anlassIds = array_map('intval', array_column(Geschenkidee::anlaesse($id), 'id'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aktion = $_POST['aktion'] ?? 'speichern';

    if ($aktion === 'loeschen') {
        Geschenkidee::loeschen($id);
        header('Location: idee-speichern.php');
        exit;
    }

    $personId = trim($_POST['person'] ?? '');
    $text = trim($_POST['text'] ?? '');
    $link = trim($_POST['link'] ?? '');
    $bildLink = trim($_POST['bild_link'] ?? '');
    $gueltigeAnlassIds = array_column(Anlass::alle(), 'id');
    $anlassIds = array_values(array_intersect(
        array_map('intval', $_POST['anlass_ids'] ?? []),
        $gueltigeAnlassIds
    ));

    $person = $personId !== '' ? Person::finden((int) $personId) : null;

    if ($person === null) {
        $fehler[] = 'Bitte eine Person auswählen.';
    }

    if (!Geschenkidee::hatInhalt($text, $link, $bildLink)) {
        $fehler[] = 'Bitte mindestens einen Inhalt angeben: Text, Link oder Bild.';
    }

    if (!Geschenkidee::istGueltigerText($text)) {
        $fehler[] = 'Die Idee enthält nicht erlaubte Inhalte oder ist zu lang (max. 1000 Zeichen).';
    }

    if (!Geschenkidee::istGueltigeUrl($link)) {
        $fehler[] = 'Bitte einen gültigen Link angeben (z. B. https://...).';
    }

    if (!Geschenkidee::istGueltigeUrl($bildLink)) {
        $fehler[] = 'Bitte einen gültigen Bild-Link angeben (z. B. https://...).';
    }

    if (empty($fehler)) {
        Geschenkidee::aktualisieren(
            $id,
            (int) $personId,
            $text !== '' ? $text : null,
            $link !== '' ? $link : null,
            $bildLink !== '' ? $bildLink : null,
            $anlassIds
        );
        header('Location: idee-speichern.php');
        exit;
    }
}

$personen = Person::alle();
$anlaesse = Anlass::alle();
$aktuellePerson = Person::finden((int) $personId);
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="css/style.css">

    <title>Geschenkidee bearbeiten</title>
</head>

<body>

    <?php include 'includes/navbar.php'; ?>

    <h1>Geschenkidee bearbeiten</h1>

    <?php foreach ($fehler as $meldung): ?>
        <p class="fehler"><?= htmlspecialchars($meldung) ?></p>
    <?php endforeach; ?>

    <form class="ideen-formular" method="post">

        <input type="hidden" name="id" value="<?= (int) $id ?>">

        <label for="person">Person:</label>
        <select id="person" name="person">
            <option value="">Person auswählen</option>
            <?php foreach ($personen as $person): ?>
                <option value="<?= (int) $person['id'] ?>" <?= (string) $person['id'] === $personId ? 'selected' : '' ?>>
                    <?= htmlspecialchars($person['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="text">Idee / Beschreibung:</label>
        <textarea id="text" name="text" maxlength="1000"><?= htmlspecialchars($text) ?></textarea>

        <label for="link">Link:</label>
        <input type="url" id="link" name="link" value="<?= htmlspecialchars($link) ?>">

        <label for="bild_link">Bild (Link):</label>
        <input type="url" id="bild_link" name="bild_link" value="<?= htmlspecialchars($bildLink) ?>">

        <label for="anlass_ids">Weitere Anlässe (optional, Mehrfachauswahl möglich):</label>
        <select id="anlass_ids" name="anlass_ids[]" multiple size="8">
            <?php foreach ($anlaesse as $anlass): ?>
                <option value="<?= (int) $anlass['id'] ?>" <?= in_array((int) $anlass['id'], $anlassIds, true) ? 'selected' : '' ?>><?= htmlspecialchars($anlass['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($aktuellePerson !== null): ?>
            <p>Die Idee gilt automatisch auch als Geburtstagsidee für <?= htmlspecialchars($aktuellePerson['name']) ?> -
                dafür ist keine gesonderte Auswahl nötig.</p>
        <?php endif; ?>

        <button type="submit" name="aktion" value="speichern">Änderungen speichern</button>

        <button type="submit" name="aktion" value="loeschen">Idee löschen</button>

    </form>

    <a href="idee-speichern.php">Zurück zu den Geschenkideen</a>

</body>

</html>
