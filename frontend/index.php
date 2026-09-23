

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
                    <a href="person-anlegen.php">Person anlegen</a>
                    <a href="person-anzeigen.php">Personen anzeigen</a>
                </div>
            </section>

            <section class="startseite-karte">
                <h2>Anlässe</h2>
                <p>Anlässe erstellen sowie bereits gespeicherte Anlässe anzeigen und bearbeiten.</p>

                <div class="startseite-aktionen">
                    <a href="anlass-erstellen.php">Anlass erstellen</a>
                    <a href="anlaesse.php">Anlässe anzeigen</a>
                </div>
            </section>

          <section class="startseite-karte">
    <h2>Geschenkideen</h2>
    <p>Neue Geschenkideen speichern und bereits vorhandene Ideen verwalten.</p>

    <div class="startseite-aktionen">
        <a href="idee-speichern.php">Geschenkideen verwalten</a>
        <a href="ideen-generieren.php">Geschenke generieren</a>
    </div>
</section>

            <section class="startseite-karte">
                <h2>Gesamtübersicht</h2>
                <p>Alle Personen, Anlässe und Geschenkideen in einer gemeinsamen Übersicht anzeigen.</p>

                <div class="startseite-aktionen">
                    <a href="gesamtliste.php">Gesamtliste anzeigen</a>
                </div>
            </section>

        </div>
    </div>
</body>

</html>
