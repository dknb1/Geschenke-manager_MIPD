<?php
require_once __DIR__ . '/../backend/models/Person.php';
require_once __DIR__ . '/../backend/models/Ruecksprung.php';

$zurueck = Ruecksprung::ausAnfrage('index.php');
$personen = Person::alle();
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Personen anzeigen</title>

    <link rel="stylesheet" href="css/style.css">
</head>

<body>
 <?php $navZurueck = $zurueck; include 'includes/navbar.php'; ?>

    <h1>Angelegte Personen</h1>

    <a href="<?= htmlspecialchars(Ruecksprung::anhaengen('person-anlegen.php', 'person-anzeigen.php')) ?>" class="aktions-button">Neue Person anlegen</a>

    <?php if (empty($personen)): ?>
        <p>Es wurden noch keine Personen angelegt.</p>
    <?php else: ?>
        <?php foreach ($personen as $person): ?>
            <a class="personen-eintrag" href="<?= htmlspecialchars(Ruecksprung::anhaengen('person-bearbeiten.php?id=' . (int) $person['id'], 'person-anzeigen.php')) ?>">
                <span class="personen-name"><?= htmlspecialchars($person['name']) ?></span>
                <span class="personen-info">
                    <?= htmlspecialchars((new DateTimeImmutable($person['geburtsdatum']))->format('d.m.Y')) ?>
                    · <?= Person::alterAlsText($person) ?>
                </span>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>

</body>

</html>
