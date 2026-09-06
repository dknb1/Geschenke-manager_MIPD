<?php
require_once __DIR__ . '/../backend/models/Anlass.php';
require_once __DIR__ . '/../backend/models/Person.php';

// Geburtstage sind fachlich Anlaesse, werden aber nicht als eigene
// Zeile in der anlaesse-Tabelle dupliziert (siehe Person::geburtstagAlsAnlass()) - deshalb
// hier live aus Person::alle() eingeblendet und ueber dieselbe naechstesVorkommen()-Logik
// wie Weihnachten einsortiert.
$anlaesse = array_map(
    static fn (array $a): array => $a + ['ist_geburtstag' => false, 'person_id' => null],
    Anlass::alle()
);
$geburtstage = array_map(
    static fn (array $p): array => Person::geburtstagAlsAnlass($p),
    Person::alle()
);
$anlaesse = array_merge($anlaesse, $geburtstage);
usort(
    $anlaesse,
    fn (array $a, array $b) => Anlass::naechstesVorkommen($a) <=> Anlass::naechstesVorkommen($b)
);
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

    <?php if (empty($anlaesse)): ?>
        <p>Es wurden noch keine Anlässe angelegt.</p>
    <?php else: ?>
        <?php foreach ($anlaesse as $anlass): ?>
            <?php if ($anlass['ist_geburtstag']): ?>
                <a href="person-bearbeiten.php?id=<?= (int) $anlass['person_id'] ?>">
                    <?= htmlspecialchars($anlass['name']) ?> - <?= htmlspecialchars(Anlass::naechstesVorkommen($anlass)->format('d.m.Y')) ?>
                    <strong>(Geburtstag)</strong>
                </a>
            <?php else: ?>
                <?php $verknuepftePersonen = Anlass::personen((int) $anlass['id']); ?>
                <a href="anlass-bearbeiten.php?id=<?= (int) $anlass['id'] ?>">
                    <?= htmlspecialchars($anlass['name']) ?> - <?= htmlspecialchars(Anlass::naechstesVorkommen($anlass)->format('d.m.Y')) ?><?php if (!empty($verknuepftePersonen)): ?> (<?= htmlspecialchars(implode(', ', array_column($verknuepftePersonen, 'name'))) ?>)<?php endif; ?><?php if ((int) $anlass['geschuetzt'] === 1): ?> <strong>(Pflichtanlass)</strong><?php endif; ?>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>

    <a href="anlass-erstellen.php">Neuen Anlass erstellen</a>

    <a href="index.php">Zurück zur Startseite</a>

</body>

</html>
