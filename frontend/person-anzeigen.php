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

    <?php if (empty($personen)): ?>
        <p>Es wurden noch keine Personen angelegt.</p>
    <?php else: ?>
        <?php foreach ($personen as $person): ?>
            <p>
                <a href="person-bearbeiten.php?id=<?= (int) $person['id'] ?>"><?= htmlspecialchars($person['name']) ?></a>
                (Geburtstag: <?= htmlspecialchars($person['geburtsdatum']) ?>, <?= Person::alter($person) ?> Jahre)
            </p>
        <?php endforeach; ?>
    <?php endif; ?>

    <a href="person-anlegen.php">Neue Person anlegen</a>

</body>

</html>
