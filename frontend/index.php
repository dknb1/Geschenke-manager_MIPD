<?php
require_once __DIR__ . '/../backend/models/Ruecksprung.php';
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/style.css">
    <title>Geschenke-Manager</title>
</head>

<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="page-container startseite">
        <h1>Geschenke-Manager</h1>

        <div class="startseite-menu">

            <section class="startseite-karte">
                <h2>Personen</h2>
                <p>Personen anlegen sowie vorhandene Personen anzeigen und bearbeiten.</p>

                <div class="startseite-aktionen">
                    <a href="<?= htmlspecialchars(Ruecksprung::anhaengen('person-anlegen.php', 'index.php')) ?>">Person anlegen</a>
                    <a href="<?= htmlspecialchars(Ruecksprung::anhaengen('person-anzeigen.php', 'index.php')) ?>">Personen anzeigen</a>
                </div>
            </section>

            <section class="startseite-karte">
                <h2>Anlässe</h2>
                <p>Anlässe erstellen sowie bereits gespeicherte Anlässe anzeigen und bearbeiten.</p>

                <div class="startseite-aktionen">
                    <a href="<?= htmlspecialchars(Ruecksprung::anhaengen('anlass-erstellen.php', 'index.php')) ?>">Anlass erstellen</a>
                    <a href="<?= htmlspecialchars(Ruecksprung::anhaengen('anlaesse.php', 'index.php')) ?>">Anlässe anzeigen</a>
                </div>
            </section>

            <section class="startseite-karte">
                <h2>Geschenke und Ideen</h2>
                <p>Neue Geschenkideen für eine Person erstellen oder Ideen generieren lassen.</p>

                <div class="startseite-aktionen">
                    <a href="<?= htmlspecialchars(Ruecksprung::anhaengen('idee-speichern.php', 'index.php')) ?>">Geschenkidee erstellen</a>
                    <a href="<?= htmlspecialchars(Ruecksprung::anhaengen('ideen-generieren.php', 'index.php')) ?>">Geschenkideen generieren</a>
                </div>
            </section>

            <section class="startseite-karte">
                <h2>Gesamtübersicht</h2>
                <p>Alle Personen, Anlässe, Geschenke und Ideen in einer gemeinsamen Übersicht anzeigen.</p>

                <div class="startseite-aktionen">
                    <a href="<?= htmlspecialchars(Ruecksprung::anhaengen('gesamtliste.php', 'index.php')) ?>">Gesamtliste anzeigen</a>
                </div>
            </section>

        </div>
    </div>
</body>

</html>
