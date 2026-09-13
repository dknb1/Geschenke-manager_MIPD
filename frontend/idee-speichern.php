<?php
require_once __DIR__ . '/../backend/models/Person.php';
require_once __DIR__ . '/../backend/models/Anlass.php';
require_once __DIR__ . '/../backend/models/Geschenkidee.php';

$fehler = [];
$aktion = $_POST['aktion'] ?? '';
$personId = trim($_POST['person'] ?? ($_GET['person'] ?? ''));
$text = '';
$link = '';
$bildLink = '';
$fuerGeburtstag = false;
$besorgt = false;
$offeneAufgaben = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $text = trim($_POST['text'] ?? '');
    $link = trim($_POST['link'] ?? '');
    $bildLink = trim($_POST['bild_link'] ?? '');
    $fuerGeburtstag = isset($_POST['fuer_geburtstag']);
    $besorgt = isset($_POST['besorgt']);
    $offeneAufgaben = trim($_POST['offene_aufgaben'] ?? '');
}

$person = $personId !== '' ? Person::finden((int) $personId) : null;

// Nur Anlaesse DIESER Person (plus geschuetzte, die fachlich immer alle betreffen) zur Auswahl
// anbieten statt der kompletten Anlass-Liste aller Personen - sonst muesste man aus einer
// potenziell langen, personenfremden Liste raten, welche Anlaesse ueberhaupt zur gewaehlten
// Person passen. Siehe Anlass::vonPerson()/geschuetzte(), analog zu gesamtliste.php.
$gueltigeAnlaesse = $person !== null ? Anlass::vonPersonInklGeschuetzte((int) $personId) : [];
$gueltigeAnlassIds = array_column($gueltigeAnlaesse, 'id');
// Absichtlich IMMER gegen die aktuell gueltigen IDs der (ggf. gerade erst gewaehlten) Person
// gefiltert, nicht nur beim expliziten "Anlaesse laden" - falls die Person zwischen zwei
// Anfragen gewechselt wurde, fallen dadurch automatisch nicht mehr passende Auswahlen raus,
// statt versehentlich Anlaesse der vorherigen Person zu speichern.
$anlassIds = array_values(array_intersect(
    array_map('intval', $_POST['anlass_ids'] ?? []),
    $gueltigeAnlassIds
));

// Der Anlass-Dialog (<dialog open>, siehe unten) wird nur dann angezeigt, wenn er entweder
// gerade explizit ueber den "Anlass auswählen"-Button neu geladen wurde, oder die Person schon
// beim Seitenaufruf feststand (Direktlink von person-bearbeiten.php mit ?person=ID, siehe dort).
// <dialog open> statt popovertarget, weil sich ein popover ohne JavaScript nicht automatisch
// nach einem Formular-Reload oeffnen laesst - <dialog open> ist dagegen ein normales,
// deklaratives HTML-Attribut und erscheint direkt in der Serverantwort. Bei jedem anderen POST
// (z. B. einem fehlgeschlagenen Speichern-Versuch nach Personenwechsel, oder dem "Fertig"-
// Button im Dialog) wird der Dialog bewusst NICHT angezeigt, damit er nie versehentlich die
// Anlaesse einer inzwischen abgewaehlten Person zeigt - ohne JavaScript kann eine Aenderung
// der Personenauswahl sonst nicht erkannt werden. Erneutes Klicken auf "Anlass auswählen"
// laedt dann einfach neu, diesmal mit der aktuell gewaehlten Person.
$anlaesseGeladen = $person !== null
    && ($aktion === 'anlaesse_laden' || $_SERVER['REQUEST_METHOD'] !== 'POST');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $aktion === 'speichern') {
    $fehler = Geschenkidee::validiereEingabe($person, $text, $link, $bildLink, $offeneAufgaben);

    if (empty($fehler)) {
        Geschenkidee::erstellen(
            (int) $personId,
            $text !== '' ? $text : null,
            $link !== '' ? $link : null,
            $bildLink !== '' ? $bildLink : null,
            $anlassIds,
            $fuerGeburtstag,
            $besorgt,
            $offeneAufgaben !== '' ? $offeneAufgaben : null
        );
        header('Location: idee-speichern.php');
        exit;
    }
}

$personen = Person::alle();
$ideen = Geschenkidee::alle();
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="css/style.css">

    <title>Geschenkidee speichern</title>
</head>

<body>

    <?php include 'includes/navbar.php'; ?>

    <h1>Geschenkidee speichern</h1>

    <?php foreach ($fehler as $meldung): ?>
        <p class="fehler"><?= htmlspecialchars($meldung) ?></p>
    <?php endforeach; ?>

    <form class="ideen-formular" method="post">

        <label for="person">Person:</label>
        <select id="person" name="person">
            <option value="">Person auswählen</option>
            <?php foreach ($personen as $p): ?>
                <option value="<?= (int) $p['id'] ?>" <?= (string) $p['id'] === $personId ? 'selected' : '' ?>>
                    <?= htmlspecialchars($p['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" name="aktion" value="anlaesse_laden">
            Anlass auswählen<?= !empty($anlassIds) ? ' (' . count($anlassIds) . ' ausgewählt)' : '' ?>
        </button>

        <?php if ($aktion === 'anlaesse_laden' && $person === null): ?>
            <p class="hinweis">Bitte zuerst eine Person auswählen.</p>
        <?php endif; ?>

        <?php if ($anlaesseGeladen): ?>

            <dialog open class="anlass-dialog">

                <div class="fenster-kopf">
                    <h3>Anlässe von <?= htmlspecialchars($person['name']) ?></h3>
                </div>

                <label class="anlass-checkbox">
                    <input type="checkbox" name="fuer_geburtstag" value="1" <?= $fuerGeburtstag ? 'checked' : '' ?>>
                    Geburtstag
                </label>

                <?php if (empty($gueltigeAnlaesse)): ?>
                    <p>Keine weiteren Anlässe für diese Person hinterlegt.</p>
                <?php else: ?>
                    <?php foreach ($gueltigeAnlaesse as $anlass): ?>
                        <label class="anlass-checkbox">
                            <input type="checkbox" name="anlass_ids[]" value="<?= (int) $anlass['id'] ?>" <?= in_array((int) $anlass['id'], $anlassIds, true) ? 'checked' : '' ?>>
                            <?= htmlspecialchars($anlass['name']) ?>
                        </label>
                    <?php endforeach; ?>
                <?php endif; ?>

                <button type="submit" name="aktion" value="anlaesse_schliessen">Fertig</button>

            </dialog>

        <?php else: ?>

            <?php /* Solange der Dialog nicht angezeigt wird, existieren seine Checkboxen nicht
                     im DOM und wuerden beim naechsten Submit (z. B. "Idee speichern") sonst gar
                     nicht mitgeschickt - die bereits getroffene Auswahl ginge verloren. Als
                     verstecktes Feld weiterreichen, bis der Dialog das naechste Mal offen ist. */ ?>
            <?php foreach ($anlassIds as $gewaehlteAnlassId): ?>
                <input type="hidden" name="anlass_ids[]" value="<?= (int) $gewaehlteAnlassId ?>">
            <?php endforeach; ?>
            <?php if ($fuerGeburtstag): ?>
                <input type="hidden" name="fuer_geburtstag" value="1">
            <?php endif; ?>

        <?php endif; ?>

        <label for="text">Idee / Beschreibung:</label>
        <textarea id="text" name="text" maxlength="1000"><?= htmlspecialchars($text) ?></textarea>

        <label for="link">Link:</label>
        <input type="url" id="link" name="link" value="<?= htmlspecialchars($link) ?>">

        <label for="bild_link">Bild (Link):</label>
        <input type="url" id="bild_link" name="bild_link" value="<?= htmlspecialchars($bildLink) ?>">

        <label>
            <input type="checkbox" name="besorgt" value="1" <?= $besorgt ? 'checked' : '' ?>>
            Geschenk bereits besorgt
        </label>

        <label for="offene_aufgaben">Offene Aufgaben:</label>
        <textarea id="offene_aufgaben" name="offene_aufgaben" maxlength="1000"><?= htmlspecialchars($offeneAufgaben) ?></textarea>

        <button type="submit" name="aktion" value="speichern">Idee speichern</button>

    </form>

    <h2>Gespeicherte Ideen</h2>

    <?php if (empty($ideen)): ?>
        <p>Es wurden noch keine Geschenkideen gespeichert.</p>
    <?php else: ?>
        <ul class="ideen-liste">
            <?php foreach ($ideen as $idee): ?>
                <?php $anlassNamen = Geschenkidee::anlassNamenInklGeburtstag((int) $idee['id']); ?>
                <li>
                    <a href="idee-bearbeiten.php?id=<?= (int) $idee['id'] ?>">
                        <strong><?= htmlspecialchars($idee['person_name']) ?>:</strong>
                        <?php if (!empty($idee['text'])): ?>
                            <?= htmlspecialchars($idee['text']) ?>
                        <?php endif; ?>
                        <?php if (!empty($anlassNamen)): ?>
                            (<?= htmlspecialchars(implode(', ', $anlassNamen)) ?>)
                        <?php endif; ?>
                    </a>
                    <?php if (!empty($idee['link']) || !empty($idee['bild_link'])): ?>
                        <div class="ideen-liste-extras">
                            <?php if (!empty($idee['link'])): ?>
                                <a href="<?= htmlspecialchars($idee['link']) ?>" target="_blank" rel="noopener noreferrer">Link</a>
                            <?php endif; ?>
                            <?php if (!empty($idee['bild_link'])): ?>
                                <a href="<?= htmlspecialchars($idee['bild_link']) ?>" target="_blank" rel="noopener noreferrer">Bild</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

</body>

</html>
