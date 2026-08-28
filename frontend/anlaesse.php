<?php
require_once __DIR__ . '/../backend/models/Anlass.php';

$fehler = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = filter_input(INPUT_POST, 'anlass', FILTER_VALIDATE_INT);
    $aktion = $_POST['aktion'] ?? '';

    if ($id === null || $id === false) {
        $fehler = 'Bitte zuerst einen Anlass auswählen.';
    } elseif ($aktion === 'loeschen') {
        if (Anlass::loeschen($id)) {
            header('Location: anlaesse.php');
            exit;
        }
        $fehler = 'Dieser Anlass ist ein Pflichtanlass und kann nicht gelöscht werden.';
    } elseif ($aktion === 'bearbeiten') {
        header('Location: anlass-bearbeiten.php?id=' . $id);
        exit;
    }
}

$anlaesse = Anlass::alle();
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Meine Anlässe</title>

    <link rel="stylesheet" href="css/style.css">
</head>

<body>
    
 <?php include 'includes/navbar.php'; ?>

    <h1>Meine Anlässe</h1>

    <?php if ($fehler): ?>
        <p class="fehler"><?= htmlspecialchars($fehler) ?></p>
    <?php endif; ?>

    <?php if (empty($anlaesse)): ?>
        <p>Es wurden noch keine Anlässe angelegt.</p>
    <?php else: ?>
        <form method="post">

            <?php foreach ($anlaesse as $anlass): ?>
                <label>
                    <input type="radio" name="anlass" value="<?= (int) $anlass['id'] ?>">
                    <?= htmlspecialchars($anlass['name']) ?> - <?= htmlspecialchars(Anlass::naechstesVorkommen($anlass)->format('d.m.Y')) ?><?php if (!empty($anlass['person_name'])): ?> (<?= htmlspecialchars($anlass['person_name']) ?>)<?php endif; ?><?php if ((int) $anlass['geschuetzt'] === 1): ?> <strong>(Pflichtanlass)</strong><?php endif; ?>
                </label>
            <?php endforeach; ?>

            <button type="submit" name="aktion" value="bearbeiten">Anlass bearbeiten</button>

            <button type="submit" name="aktion" value="loeschen">Anlass löschen</button>

        </form>
    <?php endif; ?>

    <a href="anlass-erstellen.php">Neuen Anlass erstellen</a>

    <a href="index.html">Zurück zur Startseite</a>

</body>

</html>
