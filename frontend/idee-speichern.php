<?php
require_once __DIR__ . '/../backend/models/Person.php';
require_once __DIR__ . '/../backend/models/Anlass.php';
require_once __DIR__ . '/../backend/models/Geschenkidee.php';
require_once __DIR__ . '/../backend/models/Ruecksprung.php';

// Nach dem Speichern zurueck zur aufrufenden Seite, sonst zur gewaehlten Person.
$zurueck = Ruecksprung::ausAnfrage('index.php');

$fehler = [];
$aktion = $_POST['aktion'] ?? '';
$personId = trim($_POST['person'] ?? ($_GET['person'] ?? ''));
$text = '';
$link = '';
$bildLink = '';
$fuerGeburtstag = false;
$besorgt = false;
$bereitsVerschenkt = false;
$offeneAufgaben = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $text = trim($_POST['text'] ?? '');
    $link = trim($_POST['link'] ?? '');
    $bildLink = trim($_POST['bild_link'] ?? '');
    $fuerGeburtstag = isset($_POST['fuer_geburtstag']);
    $besorgt = isset($_POST['besorgt']);
    $bereitsVerschenkt = isset($_POST['bereits_verschenkt']);
    $offeneAufgaben = trim($_POST['offene_aufgaben'] ?? '');
}

$person = $personId !== '' ? Person::finden((int) $personId) : null;

// Nur Anlaesse der gewaehlten Person anbieten.
$gueltigeAnlaesse = $person !== null ? Anlass::vonPersonInklGeschuetzte((int) $personId) : [];
$gueltigeAnlassIds = array_column($gueltigeAnlaesse, 'id');
// Immer gegen die aktuelle Person filtern, falls sie zwischendurch gewechselt wurde.
$anlassIds = array_values(array_intersect(
    array_map('intval', $_POST['anlass_ids'] ?? []),
    $gueltigeAnlassIds
));

// Dialog nur nach Klick auf den Button oder beim Aufruf mit fester Person zeigen. <dialog open>,
// weil sich ein Popover ohne JavaScript nach dem Neuladen nicht von selbst oeffnet.
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
            $offeneAufgaben !== '' ? $offeneAufgaben : null,
            $bereitsVerschenkt
        );
        header('Location: ' . ($zurueck !== '' ? $zurueck : 'person-bearbeiten.php?id=' . (int) $personId));
        exit;
    }
}

$personen = Person::alle();
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="css/style.css">

    <title>Geschenkidee erstellen</title>
</head>

<body>

    <?php $navZurueck = $zurueck; include 'includes/navbar.php'; ?>

    <h1>Geschenkidee erstellen</h1>

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

        <?php /* Enter speichert: der erste Submit-Button im Formular ist der Standard (siehe idee-bearbeiten.php). */ ?>
        <button type="submit" name="aktion" value="speichern" class="standard-button" tabindex="-1" aria-hidden="true">Geschenkidee speichern</button>

        <?php if ($aktion === 'anlaesse_laden' && $person === null): ?>
            <p class="fehler">Bitte zuerst eine Person auswählen.</p>
        <?php endif; ?>

        <button type="submit" name="aktion" value="anlaesse_laden">
            <?php $anzahlAusgewaehlt = count($anlassIds) + ($fuerGeburtstag ? 1 : 0); /* Geburtstag zaehlt mit. */ ?>Anlässe für Geschenkidee auswählen<?= $anzahlAusgewaehlt > 0 ? ' (' . $anzahlAusgewaehlt . ' ausgewählt)' : '' ?>
        </button>

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

            <?php /* Dialog zu: Auswahl als versteckte Felder weitergeben, sonst ginge sie verloren. */ ?>
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

        <label>
            <input type="checkbox" name="bereits_verschenkt" value="1" <?= $bereitsVerschenkt ? 'checked' : '' ?>>
            Wurde bereits verschenkt
        </label>

        <label for="offene_aufgaben">Offene Aufgaben:</label>
        <textarea id="offene_aufgaben" name="offene_aufgaben" maxlength="1000"><?= htmlspecialchars($offeneAufgaben) ?></textarea>

        <button type="submit" name="aktion" value="speichern">Geschenkidee speichern</button>

    </form>

</body>

</html>
