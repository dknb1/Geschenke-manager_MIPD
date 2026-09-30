<?php
// PHP-Session nur fuer den Zustimmungshinweis vor der LLM-Nutzung - bewusst session- statt
// DB-gebunden: die Zustimmung gilt nur fuer diesen Browser/diese Sitzung, nicht dauerhaft fuer
// alle, die die Anwendung nutzen (siehe Aenderungsprotokoll 2026-09-13).
session_start();

require_once __DIR__ . '/../backend/models/Person.php';
require_once __DIR__ . '/../backend/models/Geschenkidee.php';
require_once __DIR__ . '/../backend/models/Ideengenerator.php';

$id = filter_input(INPUT_GET, 'person', FILTER_VALIDATE_INT)
    ?: filter_input(INPUT_POST, 'person', FILTER_VALIDATE_INT);

$personen = Person::alle();
$person = $id ? Person::finden((int) $id) : null;

$fehler = null;
$vorschlaege = null;
$anfrageDebug = null;
$antwortDebug = null;

if ($id && $person === null) {
    $fehler = 'Die ausgewählte Person konnte nicht gefunden werden.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $person !== null) {
    $aktion = $_POST['aktion'] ?? '';

    if ($aktion === 'generieren') {
        $_SESSION['ideen_generierung_zugestimmt'] = true;

        if (!Person::darfIdeenGenerieren($person)) {
            $fehler = 'Bitte kurz warten, bevor für diese Person erneut Ideen generiert werden.';
        } else {
            $beispiele = Geschenkidee::datengrundlageFuerGenerierung((int) $id);

            if (empty($beispiele)) {
                $fehler = 'Für diese Person sind noch keine Geschenkideen hinterlegt - lege zuerst mindestens eine Idee an.';
            } else {
                $vorschlaege = Ideengenerator::generiere($beispiele);
                $anfrageDebug = Ideengenerator::letzteAnfrage();
                $antwortDebug = Ideengenerator::letzteAntwort();

                if ($vorschlaege === null) {
                    // Cooldown bewusst NUR bei Erfolg setzen - ein fehlgeschlagener Versuch
                    // (z. B. Netzwerkfehler, Rate-Limit) soll nicht zusaetzlich dafuer
                    // "bestrafen", dass man gleich nochmal versuchen will.
                    $fehler = 'Die Ideengenerierung ist gerade nicht verfügbar. Bitte später erneut versuchen.';
                } else {
                    Person::ideenGenerierungVermerken((int) $id);
                }
            }
        }
    }

    if ($aktion === 'speichern') {
        foreach ($_POST['vorschlaege'] ?? [] as $text) {
            $text = trim($text);

            if ($text !== '' && Geschenkidee::istGueltigerText($text)) {
                Geschenkidee::erstellen((int) $id, $text, null, null);
            }
        }

        header('Location: person-bearbeiten.php?id=' . (int) $id);
        exit;
    }
}

$zugestimmt = !empty($_SESSION['ideen_generierung_zugestimmt']);
?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Geschenkideen generieren</title>
    <link rel="stylesheet" href="css/style.css">
</head>

<body>

<?php $navZurueck = $person !== null ? 'person-bearbeiten.php?id=' . (int) $id : 'index.php'; include 'includes/navbar.php'; ?>

<div class="page-container">

    <?php if ($person === null): ?>

        <h1>Geschenkideen generieren</h1>
        <p>Wähle eine Person aus, für die neue Geschenkideen generiert werden sollen.</p>

        <?php if ($fehler !== null): ?>
            <p class="fehler"><?= htmlspecialchars($fehler) ?></p>
        <?php endif; ?>

        <?php if (empty($personen)): ?>
            <p class="hinweis">Es wurden noch keine Personen angelegt.</p>
            <a href="person-anlegen.php">Person anlegen</a>
        <?php else: ?>
            <form method="get" action="ideen-generieren.php" class="ideen-formular">
                <label for="person">Person auswählen:</label>

                <select id="person" name="person" required>
                    <option value="">Bitte auswählen</option>

                    <?php foreach ($personen as $p): ?>
                        <option value="<?= (int) $p['id'] ?>">
                            <?= htmlspecialchars($p['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit">Weiter</button>
            </form>
        <?php endif; ?>

        <a href="index.php">Zurück zur Startseite</a>

    <?php else: ?>

        <h1>Geschenkideen generieren für <?= htmlspecialchars($person['name']) ?></h1>

        <?php if ($fehler !== null): ?>
            <p class="fehler"><?= htmlspecialchars($fehler) ?></p>
        <?php endif; ?>

        <?php if ($vorschlaege === null): ?>

            <?php if (!$zugestimmt): ?>
                <div class="hinweis">
                    <p>
                        Für die Ideengenerierung werden die Texte der bereits verschenkten,
                        fest zugeordneten und zuletzt angelegten offenen Geschenkideen dieser
                        Person an den externen Anbieter <strong>Groq</strong> (Modell
                        <code>openai/gpt-oss-20b</code>) übermittelt, um drei neue Vorschläge
                        zu erhalten. Es werden ausschließlich die Ideen-Texte übertragen -
                        kein Personenname, keine Bilder und keine Links.
                        Die Zustimmung gilt nur für diese Browser-Sitzung.
                    </p>
                </div>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="person" value="<?= (int) $id ?>">

                <button type="submit" name="aktion" value="generieren">
                    <?= $zugestimmt ? 'Ideen generieren' : 'Verstanden, Ideen generieren' ?>
                </button>
            </form>

        <?php else: ?>

            <h2>Vorschläge</h2>
            <p>Wähle aus, welche der Vorschläge gespeichert werden sollen.</p>

            <form method="post">
                <input type="hidden" name="person" value="<?= (int) $id ?>">

                <?php foreach ($vorschlaege as $vorschlag): ?>
                    <label class="anlass-checkbox">
                        <input
                            type="checkbox"
                            name="vorschlaege[]"
                            value="<?= htmlspecialchars($vorschlag) ?>"
                            checked
                        >
                        <?= htmlspecialchars($vorschlag) ?>
                    </label>
                <?php endforeach; ?>

                <button type="submit" name="aktion" value="speichern">
                    Ausgewählte Ideen speichern
                </button>
            </form>

        <?php endif; ?>

        <?php if ($anfrageDebug !== null): ?>
            <details class="debug-details">
                <summary>Anfrage an Groq anzeigen</summary>
                <pre><?= htmlspecialchars($anfrageDebug) ?></pre>
            </details>

            <details class="debug-details">
                <summary>Antwort von Groq anzeigen</summary>
                <pre><?= htmlspecialchars($antwortDebug ?? '(keine Antwort erhalten)') ?></pre>
            </details>
        <?php endif; ?>

        <a href="ideen-generieren.php">Andere Person auswählen</a>
        <a href="person-bearbeiten.php?id=<?= (int) $id ?>">
            Zurück zu <?= htmlspecialchars($person['name']) ?>
        </a>

    <?php endif; ?>

</div>

</body>
</html>
