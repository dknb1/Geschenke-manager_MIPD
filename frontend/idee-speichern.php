<?php
require_once __DIR__ . '/../backend/models/Person.php';
require_once __DIR__ . '/../backend/models/Anlass.php';
require_once __DIR__ . '/../backend/models/Geschenkidee.php';

$fehler = [];
$personId = '';
$text = '';
$link = '';
$bildLink = '';
$anlassIds = [];
$fuerGeburtstag = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $personId = trim($_POST['person'] ?? '');
    $text = trim($_POST['text'] ?? '');
    $link = trim($_POST['link'] ?? '');
    $bildLink = trim($_POST['bild_link'] ?? '');
    $fuerGeburtstag = isset($_POST['fuer_geburtstag']);
    $gueltigeAnlassIds = array_column(Anlass::alle(), 'id');
    $anlassIds = array_values(array_intersect(
        array_map('intval', $_POST['anlass_ids'] ?? []),
        $gueltigeAnlassIds
    ));

    $person = $personId !== '' ? Person::finden((int) $personId) : null;
    $fehler = Geschenkidee::validiereEingabe($person, $text, $link, $bildLink);

    if (empty($fehler)) {
        Geschenkidee::erstellen(
            (int) $personId,
            $text !== '' ? $text : null,
            $link !== '' ? $link : null,
            $bildLink !== '' ? $bildLink : null,
            $anlassIds,
            $fuerGeburtstag
        );
        header('Location: idee-speichern.php');
        exit;
    }
}

$personen = Person::alle();
$anlaesse = Anlass::alle();
$ideen = Geschenkidee::alle();
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="css/style.css">

    <title>Geschenkidee speichern</title>
</head>

<body>

    <?php include 'includes/navbar.php'; ?>

    <h1>Geschenkidee speichern</h1>

    <?php foreach ($fehler as $meldung): ?>
        <p class="fehler"><?= htmlspecialchars($meldung) ?></p>
    <?php endforeach; ?>

    <form class="ideen-formular" method="post">

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

        <label>
            <input type="checkbox" name="fuer_geburtstag" value="1" <?= $fuerGeburtstag ? 'checked' : '' ?>>
            Diese Idee ist auch für den Geburtstag der ausgewählten Person gedacht
        </label>

        <button type="submit">Idee speichern</button>

    </form>

    <h2>Gespeicherte Ideen</h2>

    <?php if (empty($ideen)): ?>
        <p>Es wurden noch keine Geschenkideen gespeichert.</p>
    <?php else: ?>
        <ul class="ideen-liste">
            <?php foreach ($ideen as $idee): ?>
                <?php $anlassNamen = Geschenkidee::anlassNamenInklGeburtstag((int) $idee['id']); ?>
                <li>
                    <a href="idee-bearbeiten.php?id=<?= (int) $idee['id'] ?>">
                        <strong><?= htmlspecialchars($idee['person_name']) ?>:</strong>
                        <?php if (!empty($idee['text'])): ?>
                            <?= htmlspecialchars($idee['text']) ?>
                        <?php endif; ?>
                        <?php if (!empty($anlassNamen)): ?>
                            (<?= htmlspecialchars(implode(', ', $anlassNamen)) ?>)
                        <?php endif; ?>
                    </a>
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

</body>

</html>
