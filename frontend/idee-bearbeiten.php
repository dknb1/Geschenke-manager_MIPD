<?php
require_once __DIR__ . '/../backend/models/Person.php';
require_once __DIR__ . '/../backend/models/Anlass.php';
require_once __DIR__ . '/../backend/models/Geschenkidee.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    header('Location: idee-speichern.php');
    exit;
}

$idee = Geschenkidee::finden($id);

if (!$idee) {
    header('Location: idee-speichern.php');
    exit;
}

$fehler = [];
$personId = (string) $idee['person_id'];
$text = $idee['text'] ?? '';
$link = $idee['link'] ?? '';
$bildLink = $idee['bild_link'] ?? '';
$anlassIds = array_map('intval', array_column(Geschenkidee::anlaesse($id), 'id'));
$fuerGeburtstag = (int) $idee['fuer_geburtstag'] === 1;
$besorgt = (int) ($idee['besorgt'] ?? 0) === 1;
$offeneAufgaben = $idee['offene_aufgaben'] ?? '';

// Anlaesse, die dieser Person zugeordnet werden duerfen - nicht Anlass::alle(), sonst muesste
// man aus der kompletten, personenfremden Liste raten, welche Anlaesse ueberhaupt zu dieser
// Person passen (siehe Anlass::vonPersonInklGeschuetzte()).
$gueltigeAnlaesse = $personId !== '' ? Anlass::vonPersonInklGeschuetzte((int) $personId) : [];

// Fuer "Fest zuordnen" zusaetzlich auf noch nicht vergangene Anlaesse eingeschraenkt (siehe
// Geschenkidee::istGueltigerZielAnlass()) - bei wiederkehrenden Anlaessen ohnehin immer erfuellt.
$heute = new DateTimeImmutable('today');
$gueltigeFestAnlaesse = array_values(array_filter(
    $gueltigeAnlaesse,
    fn (array $a) => Geschenkidee::istGueltigerZielAnlass($a, $heute)
));

$aktion = $_POST['aktion'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($aktion === '') {
        $aktion = 'speichern';
    }

    if ($aktion === 'loeschen') {
        Geschenkidee::loeschen($id);
        header('Location: idee-speichern.php');
        exit;
    }

    if ($aktion === 'fest_machen') {
        $zielWert = trim($_POST['geschenk_anlass_id'] ?? '');

        if ($zielWert === 'geburtstag') {
            Geschenkidee::festMachenFuerGeburtstag($id);
            header('Location: idee-bearbeiten.php?id=' . $id);
            exit;
        }

        $zielAnlassId = filter_var($zielWert, FILTER_VALIDATE_INT);
        $gueltigeFestAnlassIds = array_column($gueltigeFestAnlaesse, 'id');

        if ($zielAnlassId && in_array($zielAnlassId, $gueltigeFestAnlassIds, true)) {
            Geschenkidee::festMachen($id, $zielAnlassId);
            header('Location: idee-bearbeiten.php?id=' . $id);
            exit;
        }

        $fehler[] = 'Bitte einen gültigen Anlass auswählen (kein Anlass in der Vergangenheit).';
    }

    if ($aktion === 'offen_setzen') {
        Geschenkidee::zurueckAufOffen($id);
        header('Location: idee-bearbeiten.php?id=' . $id);
        exit;
    }

    if ($aktion === 'vergangen_machen') {
        $zielWert = trim($_POST['vergangen_anlass'] ?? '');
        $datum = trim($_POST['vergangen_datum'] ?? '');

        if ($zielWert === 'geburtstag') {
            $erfolg = Geschenkidee::vergangenMachenFuerGeburtstag($id, $datum);
        } else {
            $anlassId = filter_var($zielWert, FILTER_VALIDATE_INT);
            $erfolg = $anlassId
                ? Geschenkidee::vergangenMachen($id, $anlassId, $datum)
                : false;
        }

        if ($erfolg) {
            header('Location: idee-bearbeiten.php?id=' . $id);
            exit;
        }

        $fehler[] = 'Bitte einen gültigen Anlass und ein Datum in der Vergangenheit angeben.';
    }

    if ($aktion === 'speichern') {
        $personId = trim($_POST['person'] ?? '');
        $text = trim($_POST['text'] ?? '');
        $link = trim($_POST['link'] ?? '');
        $bildLink = trim($_POST['bild_link'] ?? '');
        $fuerGeburtstag = isset($_POST['fuer_geburtstag']);
        $besorgt = isset($_POST['besorgt']);
        $offeneAufgaben = trim($_POST['offene_aufgaben'] ?? '');
        // Neu anhand der (evtl. gerade geaenderten) Person berechnet statt der oben fuer
        // fest_machen() ermittelten Liste - falls die Person hier gewechselt wurde, sollen
        // nur noch deren Anlaesse als gueltig gelten.
        $gueltigeAnlaesse = $personId !== '' ? Anlass::vonPersonInklGeschuetzte((int) $personId) : [];
        $gueltigeAnlassIds = array_column($gueltigeAnlaesse, 'id');
        $anlassIds = array_values(array_intersect(
            array_map('intval', $_POST['anlass_ids'] ?? []),
            $gueltigeAnlassIds
        ));

        $person = $personId !== '' ? Person::finden((int) $personId) : null;
        $fehler = Geschenkidee::validiereEingabe($person, $text, $link, $bildLink, $offeneAufgaben);

        if (empty($fehler)) {
            Geschenkidee::aktualisieren(
                $id,
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
}

$personen = Person::alle();
$aktuellePerson = Person::finden((int) $personId);

// Siehe idee-speichern.php fuer die ausfuehrliche Begruendung: der <dialog open>-Anlass-Dialog
// wird nur dann angezeigt, wenn er entweder gerade explizit neu geladen wurde, oder die Person
// schon beim Seitenaufruf feststand (hier: beim ersten Laden immer der Fall, da die Idee
// bereits eine Person hat) - so zeigt er nie versehentlich die Anlaesse einer inzwischen
// geaenderten Person.
$anlaesseGeladen = $aktuellePerson !== null
    && ($aktion === 'anlaesse_laden' || $_SERVER['REQUEST_METHOD'] !== 'POST');

$istFest = Geschenkidee::istFest($idee);
$festName = null;
if ($istFest) {
    $festName = (int) $idee['geschenk_fuer_geburtstag'] === 1
        ? 'Geburtstag' . ($aktuellePerson !== null ? ' von ' . $aktuellePerson['name'] : '')
        : (Anlass::finden((int) $idee['geschenk_anlass_id'])['name'] ?? null);
}
$festIstVergangen = $istFest && new DateTimeImmutable($idee['geschenk_datum']) < new DateTimeImmutable('today');
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="css/style.css">

    <title>Geschenkidee bearbeiten</title>
</head>

<body>

    <?php include 'includes/navbar.php'; ?>

    <h1>Geschenkidee bearbeiten</h1>

    <?php foreach ($fehler as $meldung): ?>
        <p class="fehler"><?= htmlspecialchars($meldung) ?></p>
    <?php endforeach; ?>

    <form class="ideen-formular" method="post">

        <input type="hidden" name="id" value="<?= (int) $id ?>">

        <label for="person">Person:</label>
        <select id="person" name="person">
            <option value="">Person auswählen</option>
            <?php foreach ($personen as $p): ?>
                <option value="<?= (int) $p['id'] ?>" <?= (string) $p['id'] === $personId ? 'selected' : '' ?>>
                    <?= htmlspecialchars($p['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>Weitere Anlässe (optional):</label>

        <button type="submit" name="aktion" value="anlaesse_laden">
            Anlass auswählen<?= !empty($anlassIds) ? ' (' . count($anlassIds) . ' ausgewählt)' : '' ?>
        </button>

        <?php if ($aktion === 'anlaesse_laden' && $aktuellePerson === null): ?>
            <p class="hinweis">Bitte zuerst eine Person auswählen.</p>
        <?php endif; ?>

        <?php if ($anlaesseGeladen): ?>

            <dialog open class="anlass-dialog">

                <div class="fenster-kopf">
                    <h3>Anlässe von <?= htmlspecialchars($aktuellePerson['name']) ?></h3>
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

        <?php if ($istFest): ?>
            <p>
                Fest zugeordnet zu: <strong><?= htmlspecialchars($festName) ?></strong>
                am <?= htmlspecialchars((new DateTimeImmutable($idee['geschenk_datum']))->format('d.m.Y')) ?>
                <?php if ($festIstVergangen): ?><strong>(vergangen)</strong><?php endif; ?>
            </p>
        <?php else: ?>
            <label for="geschenk_anlass_id">Fest zuordnen zu (macht diese Idee zum Geschenk für genau diesen Anlass):</label>
            <select id="geschenk_anlass_id" name="geschenk_anlass_id">
                <option value="">Anlass auswählen</option>
                <option value="geburtstag">Geburtstag<?= $aktuellePerson !== null ? ' von ' . htmlspecialchars($aktuellePerson['name']) : '' ?></option>
                <?php foreach ($gueltigeFestAnlaesse as $anlass): ?>
                    <option value="<?= (int) $anlass['id'] ?>"><?= htmlspecialchars($anlass['name']) ?></option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>

        <?php if (!$festIstVergangen): ?>

            <h3>Als vergangenes Geschenk dokumentieren</h3>

            <label for="vergangen_anlass">Anlass:</label>
            <select id="vergangen_anlass" name="vergangen_anlass">
                <option value="">Anlass auswählen</option>
                <option value="geburtstag">
                    Geburtstag<?= $aktuellePerson !== null ? ' von ' . htmlspecialchars($aktuellePerson['name']) : '' ?>
                </option>

                <?php foreach ($gueltigeAnlaesse as $anlass): ?>
                    <option value="<?= (int) $anlass['id'] ?>">
                        <?= htmlspecialchars($anlass['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="vergangen_datum">Geschenkt am:</label>
            <input type="date"
                   id="vergangen_datum"
                   name="vergangen_datum"
                   max="<?= htmlspecialchars((new DateTimeImmutable('yesterday'))->format('Y-m-d')) ?>">

            <button type="submit" name="aktion" value="vergangen_machen">
                Als vergangenes Geschenk speichern
            </button>

        <?php endif; ?>

        <button type="submit" name="aktion" value="speichern">Änderungen speichern</button>

        <?php if ($istFest): ?>
            <button type="submit" name="aktion" value="offen_setzen">Fest-Zuordnung aufheben</button>
        <?php else: ?>
            <button type="submit" name="aktion" value="fest_machen">Fest machen</button>
        <?php endif; ?>

        <button type="submit" name="aktion" value="loeschen">Idee löschen</button>

    </form>

    <a href="idee-speichern.php">Zurück zu den Geschenkideen</a>

</body>

</html>
