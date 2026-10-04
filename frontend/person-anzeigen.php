<?php
require_once __DIR__ . '/../backend/models/Person.php';

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
 <?php include 'includes/navbar.php'; ?>

    <h1>Angelegte Personen</h1>

    <a href="person-anlegen.php" class="aktions-button">Neue Person anlegen</a>

    <?php if (empty($personen)): ?>
        <p>Es wurden noch keine Personen angelegt.</p>
    <?php else: ?>
        <?php foreach ($personen as $person): ?>
            <a class="personen-eintrag" href="person-bearbeiten.php?id=<?= (int) $person['id'] ?>">
                <span class="personen-name"><?= htmlspecialchars($person['name']) ?></span>
                <span class="personen-info">
                    <?= htmlspecialchars((new DateTimeImmutable($person['geburtsdatum']))->format('d.m.Y')) ?>
                    · <?= Person::alter($person) ?> Jahre
                </span>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>

</body>

</html>
