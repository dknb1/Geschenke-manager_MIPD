<?php
// Die Zustimmung zur Datenuebertragung gilt nur fuer diese Browser-Sitzung.
session_start();

require_once __DIR__ . '/../backend/models/Anlass.php';
require_once __DIR__ . '/../backend/models/Person.php';
require_once __DIR__ . '/../backend/models/Geschenkidee.php';
require_once __DIR__ . '/../backend/models/Ideengenerator.php';
require_once __DIR__ . '/../backend/models/Interesse.php';
require_once __DIR__ . '/../backend/models/Ruecksprung.php';

$id = filter_input(INPUT_GET, 'person', FILTER_VALIDATE_INT)
    ?: filter_input(INPUT_POST, 'person', FILTER_VALIDATE_INT);

$personen = Person::alle();
$person = $id ? Person::finden((int) $id) : null;

$standardZurueck = $person !== null ? 'person-bearbeiten.php?id=' . (int) $id : 'index.php';
$zurueck = Ruecksprung::ausAnfrage($standardZurueck);

$fehler = null;
$vorschlaege = null;
$anfrageDebug = null;
$antwortDebug = null;

if ($id && $person === null) {
    $fehler = 'Die ausgewählte Person konnte nicht gefunden werden.';
}

// Anlass nur aus der Auswahl dieser Person, Budget und Altersgruppe nur aus der festen Liste -
// so kommt kein beliebiger Text in den Prompt.
$anlassAuswahl = $person !== null ? Anlass::auswahlFuerIdeengenerierung((int) $id) : [];
$anlass = $_POST['anlass'] ?? 'keiner';
$anlass = is_string($anlass) && isset($anlassAuswahl[$anlass]) ? $anlass : 'keiner';
$budget = $_POST['budget'] ?? 'egal';
$budget = is_string($budget) && isset(Ideengenerator::BUDGETS[$budget]) ? $budget : 'egal';
$altersgruppe = $_POST['altersgruppe'] ?? 'keine';
$altersgruppe = is_string($altersgruppe) && isset(Ideengenerator::ALTERSGRUPPEN[$altersgruppe]) ? $altersgruppe : 'keine';

$beispiele = $person !== null ? Geschenkidee::datengrundlageFuerGenerierung((int) $id) : [];
$interessen = $person !== null ? Interesse::vonPerson((int) $id) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $person !== null) {
    $aktion = $_POST['aktion'] ?? '';

    if ($aktion === 'generieren') {
        $_SESSION['ideen_generierung_zugestimmt'] = true;

        if (!Person::darfIdeenGenerieren($person)) {
            $fehler = 'Bitte kurz warten, bevor für diese Person erneut Ideen generiert werden.';
        } else {
            if (empty($beispiele) && empty($interessen)) {
                $fehler = 'Für diese Person sind weder Geschenkideen noch Interessen hinterlegt - lege zuerst eine Idee an oder wähle Interessen aus.';
            } else {
                $vorschlaege = Ideengenerator::generiere(
                    $beispiele,
                    Interesse::bezeichnungen($interessen),
                    $anlass !== 'keiner' ? $anlassAuswahl[$anlass] : null,
                    $budget,
                    $altersgruppe
                );
                $anfrageDebug = Ideengenerator::letzteAnfrage();
                $antwortDebug = Ideengenerator::letzteAntwort();

                if ($vorschlaege === null) {
                    // Wartezeit nur nach Erfolg, ein Fehlversuch soll nicht zusaetzlich bremsen.
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

<?php $navZurueck = $zurueck; include 'includes/navbar.php'; ?>

<?php /* Ohne aeusseren Rahmen, sonst Kasten im Kasten. */ ?>
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
                <input type="hidden" name="zurueck" value="<?= htmlspecialchars(Ruecksprung::anhaengen('ideen-generieren.php', $zurueck)) ?>">

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

    <?php else: ?>

        <h1>Geschenkideen generieren für <?= htmlspecialchars($person['name']) ?></h1>

        <?php if ($fehler !== null): ?>
            <p class="fehler"><?= htmlspecialchars($fehler) ?></p>
        <?php endif; ?>

        <?php if ($vorschlaege === null): ?>

            <?php if (Ideengenerator::datengrundlageIstDuenn(count($beispiele), count($interessen))): ?>
                <?php /* Nach dem Speichern der Interessen zurueck hierher. */ ?>
                <div class="hinweis">
                    <p>
                        Für <?= htmlspecialchars($person['name']) ?> ist noch wenig bekannt
                        (<?= count($beispiele) ?> <?= count($beispiele) === 1 ? 'Idee' : 'Ideen' ?>, keine Interessen).
                        Die Vorschläge werden deutlich besser, wenn du Interessen hinterlegst.
                    </p>
                    <a href="<?= htmlspecialchars(Ruecksprung::anhaengen('person-bearbeiten.php?id=' . (int) $id, 'ideen-generieren.php?person=' . (int) $id)) ?>">
                        Interessen von <?= htmlspecialchars($person['name']) ?> hinterlegen
                    </a>
                </div>
            <?php endif; ?>

            <?php if (!$zugestimmt): ?>
                <div class="hinweis">
                    <p>
                        Für die Ideengenerierung werden folgende Angaben an den externen Anbieter
                        <strong>Groq</strong> (Modell <code><?= htmlspecialchars(Ideengenerator::MODELL) ?></code>) übermittelt,
                        um drei neue Vorschläge zu erhalten: die Texte der bereits verschenkten,
                        fest zugeordneten und zuletzt angelegten offenen Geschenkideen dieser
                        Person, ihre hinterlegten Interessen-Kategorien, der Name des unten
                        gewählten Anlasses (so wie er gespeichert ist), das Budget und, falls
                        ausgewählt, die Altersgruppe. Nicht
                        übertragen werden Name, Geburtsdatum bzw. genaues Alter, Geschlecht, Details,
                        Bilder und Links – außer sie stehen im Namen des gewählten Anlasses.
                        Die Zustimmung gilt nur für diese Browser-Sitzung.
                    </p>
                </div>
            <?php endif; ?>

            <form method="post" class="ideen-formular">
                <input type="hidden" name="person" value="<?= (int) $id ?>">

                <label for="anlass">Anlass:</label>
                <select id="anlass" name="anlass">
                    <?php foreach ($anlassAuswahl as $schluessel => $bezeichnung): ?>
                        <option value="<?= htmlspecialchars((string) $schluessel) ?>" <?= (string) $schluessel === $anlass ? 'selected' : '' ?>>
                            <?= htmlspecialchars($bezeichnung) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="hinweis-klein">Der Name des gewählten Anlasses wird wie angezeigt an die KI übertragen. Es sollten deshalb keine persönlichen Informationen im Namen enthalten sein.</p>

                <label for="budget">Budget (optional):</label>
                <select id="budget" name="budget">
                    <?php foreach (Ideengenerator::BUDGETS as $schluessel => $bezeichnung): ?>
                        <option value="<?= htmlspecialchars($schluessel) ?>" <?= $schluessel === $budget ? 'selected' : '' ?>>
                            <?= htmlspecialchars($bezeichnung) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="altersgruppe">Altersgruppe (optional):</label>
                <select id="altersgruppe" name="altersgruppe">
                    <?php foreach (Ideengenerator::ALTERSGRUPPEN as $schluessel => $bezeichnung): ?>
                        <option value="<?= htmlspecialchars($schluessel) ?>" <?= $schluessel === $altersgruppe ? 'selected' : '' ?>>
                            <?= htmlspecialchars($bezeichnung) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="hinweis-klein">Nur die gewählte Gruppe wird übertragen, nie das genaue Alter. Hilft z. B. bei Kindern, unpassende Vorschläge zu vermeiden.</p>

                <button type="submit" name="aktion" value="generieren">
                    <?= $zugestimmt ? 'Ideen generieren' : 'Verstanden, Ideen generieren' ?>
                </button>
            </form>

        <?php else: ?>

            <form method="post" class="ideen-formular">
                <h2>Vorschläge</h2>
                <p class="hinweis-klein">Wähle aus, welche der Vorschläge gespeichert werden sollen.</p>

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

    <?php endif; ?>

</body>
</html>
