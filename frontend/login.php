<?php
require_once __DIR__ . '/../backend/models/Anmeldung.php';

Anmeldung::sessionStarten();

if (Anmeldung::istAngemeldet()) {
    header('Location: index.php');
    exit;
}

$fehler = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $passwort = $_POST['passwort'] ?? '';

    if (is_string($passwort) && Anmeldung::anmelden($passwort)) {
        header('Location: index.php');
        exit;
    }

    $fehler = 'Das Passwort ist falsch.';
}
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">

    <title>Anmelden</title>

    <link rel="stylesheet" href="css/style.css">
</head>

<body>

    <div class="page-container login-seite">

        <h1>Geschenke-Manager</h1>

        <?php if ($fehler !== ''): ?>
            <p class="fehler"><?= htmlspecialchars($fehler) ?></p>
        <?php endif; ?>

        <form method="post" class="ideen-formular">

            <label for="passwort">Passwort:</label>
            <input type="password" id="passwort" name="passwort" autocomplete="current-password" required autofocus>

            <button type="submit">Anmelden</button>

        </form>

    </div>

</body>

</html>
