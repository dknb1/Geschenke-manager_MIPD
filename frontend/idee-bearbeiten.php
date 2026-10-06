<?php
require_once __DIR__ . '/../backend/models/Person.php';
require_once __DIR__ . '/../backend/models/Anlass.php';
require_once __DIR__ . '/../backend/models/Geschenkidee.php';
require_once __DIR__ . '/../backend/models/Ruecksprung.php';

// Nach dem Speichern zurueck zur aufrufenden Seite, sonst zur Person der Idee.
$zurueck = Ruecksprung::ausAnfrage('person-anzeigen.php');

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    header('Location: ' . $zurueck);
    exit;
}

$idee = Geschenkidee::finden($id);

if (!$idee) {
    header('Location: ' . $zurueck);
    exit;
}

$zurueck = Ruecksprung::ausAnfrage('person-bearbeiten.php?id=' . (int) $idee['person_id']);

$fehler = [];
$personId = (string) $idee['person_id'];
$text = $idee['text'] ?? '';
$link = $idee['link'] ?? '';
$bildLink = $idee['bild_link'] ?? '';
$anlassIds = array_map('intval', array_column(Geschenkidee::anlaesse($id), 'id'));
$fuerGeburtstag = (int) $idee['fuer_geburtstag'] === 1;
$besorgt = (int) ($idee['besorgt'] ?? 0) === 1;
$bereitsVerschenkt = (int) ($idee['bereits_verschenkt'] ?? 0) === 1;
$offeneAufgaben = $idee['offene_aufgaben'] ?? '';

$aktion = $_POST['aktion'] ?? '';

// Alle Buttons stecken in einem Formular: Felder bei jedem POST lesen, sonst gehen Eingaben verloren.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $personId = trim($_POST['person'] ?? '');
    $text = trim($_POST['text'] ?? '');
    $link = trim($_POST['link'] ?? '');
    $bildLink = trim($_POST['bild_link'] ?? '');
    $fuerGeburtstag = isset($_POST['fuer_geburtstag']);
    $besorgt = isset($_POST['besorgt']);
    $bereitsVerschenkt = isset($_POST['bereits_verschenkt']);
    $offeneAufgaben = trim($_POST['offene_aufgaben'] ?? '');
}

// Im Dialog nur Anlaesse der gewaehlten Person anbieten.
$gueltigeAnlaesse = $personId !== '' ? Anlass::vonPersonInklGeschuetzte((int) $personId) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Gegen alle Anlaesse pruefen, damit bestehende Tags nicht verloren gehen, wenn die Person gewechselt hat.
    $anlassIds = array_values(array_intersect(
        array_map('intval', $_POST['anlass_ids'] ?? []),
        array_column(Anlass::alle(), 'id')
    ));
}

// Fest zuordnen nur zu Anlaessen, die noch bevorstehen.
$heute = new DateTimeImmutable('today');
$gueltigeFestAnlaesse = array_values(array_filter(
    $gueltigeAnlaesse,
    fn (array $a) => Geschenkidee::istGueltigerZielAnlass($a, $heute)
));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($aktion === '') {
        $aktion = 'speichern';
    }

    if ($aktion === 'loeschen') {
        Geschenkidee::loeschen($id);
        header('Location: ' . $zurueck);
        exit;
    }

    if ($aktion === 'fest_machen') {
        $zielWert = trim($_POST['geschenk_anlass_id'] ?? '');

        if ($zielWert === 'geburtstag') {
            Geschenkidee::festMachenFuerGeburtstag($id);
            header('Location: ' . Ruecksprung::anhaengen('idee-bearbeiten.php?id=' . $id, $zurueck));
            exit;
        }

        $zielAnlassId = filter_var($zielWert, FILTER_VALIDATE_INT);
        $gueltigeFestAnlassIds = array_column($gueltigeFestAnlaesse, 'id');

        if ($zielAnlassId && in_array($zielAnlassId, $gueltigeFestAnlassIds, true)) {
            Geschenkidee::festMachen($id, $zielAnlassId);
            header('Location: ' . Ruecksprung::anhaengen('idee-bearbeiten.php?id=' . $id, $zurueck));
            exit;
        }

        $fehler[] = 'Bitte einen gültigen Anlass auswählen (kein Anlass in der Vergangenheit).';
    }

    if ($aktion === 'offen_setzen') {
        Geschenkidee::zurueckAufOffen($id);
        header('Location: ' . Ruecksprung::anhaengen('idee-bearbeiten.php?id=' . $id, $zurueck));
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
            header('Location: ' . Ruecksprung::anhaengen('idee-bearbeiten.php?id=' . $id, $zurueck));
            exit;
        }

        $fehler[] = 'Bitte einen gültigen Anlass und ein Datum in der Vergangenheit angeben.';
    }

    if ($aktion === 'speichern') {
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
                $offeneAufgaben !== '' ? $offeneAufgaben : null,
                $bereitsVerschenkt
            );
            header('Location: ' . $zurueck);
            exit;
        }
    }
}

$personen = Person::alle();
$aktuellePerson = Person::finden((int) $personId);

// Dialog hier nur auf Klick oeffnen, nicht automatisch wie beim Erstellen.
$anlaesseGeladen = $aktuellePerson !== null && $aktion === 'anlaesse_laden';

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

    <?php $navZurueck = $zurueck; include 'includes/navbar.php'; ?>

    <h1>Geschenkidee bearbeiten</h1>

    <?php foreach ($fehler as $meldung): ?>
        <p class="fehler"><?= htmlspecialchars($meldung) ?></p>
    <?php endforeach; ?>

    <?php /* Ein Formular fuer alle Kaesten, damit Eingaben bei jedem Button erhalten bleiben. */ ?>
    <form method="post" class="abschnitte">

        <input type="hidden" name="id" value="<?= (int) $id ?>">

        <?php /* Enter speichert: der erste Submit-Button im Formular ist der Standard. Nur optisch
                 versteckt, manche Browser ignorieren sonst einen unsichtbaren Button. */ ?>
        <button type="submit" name="aktion" value="speichern" class="standard-button" tabindex="-1" aria-hidden="true">Änderungen speichern</button>

        <section class="ideen-formular">

            <h2>Angaben zur Idee</h2>

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

            <?php if ($istFest): ?>

                <?php /* Bei fester Zuordnung sind die Tags ausgeblendet, werden aber unveraendert weitergegeben. */ ?>
                <p class="hinweis">Ausgeblendet, solange diese Idee fest zugeordnet ist.</p>
                <?php foreach ($anlassIds as $gewaehlteAnlassId): ?>
                    <input type="hidden" name="anlass_ids[]" value="<?= (int) $gewaehlteAnlassId ?>">
                <?php endforeach; ?>
                <?php if ($fuerGeburtstag): ?>
                    <input type="hidden" name="fuer_geburtstag" value="1">
                <?php endif; ?>

            <?php else: ?>

                <?php if ($aktion === 'anlaesse_laden' && $aktuellePerson === null): ?>
                    <p class="fehler">Bitte zuerst eine Person auswählen.</p>
                <?php endif; ?>

                <button type="submit" name="aktion" value="anlaesse_laden">
                    <?php $anzahlAusgewaehlt = count($anlassIds) + ($fuerGeburtstag ? 1 : 0); /* Geburtstag zaehlt mit. */ ?>Weitere Anlässe bearbeiten<?= $anzahlAusgewaehlt > 0 ? ' (' . $anzahlAusgewaehlt . ' ausgewählt)' : '' ?>
                </button>

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

            <button type="submit" name="aktion" value="speichern">Änderungen speichern</button>

        </section>

        <section class="ideen-formular">

            <h2><?= !$istFest ? 'Als Geschenk festlegen' : ($festIstVergangen ? 'Vergangenes Geschenk' : 'Festgelegtes Geschenk') ?></h2>

            <?php if ($istFest): ?>
                <p>
                    Fest zugeordnet zu: <strong><?= htmlspecialchars($festName) ?></strong>
                    am <?= htmlspecialchars((new DateTimeImmutable($idee['geschenk_datum']))->format('d.m.Y')) ?>
                    <?php if ($festIstVergangen): ?><strong>(vergangen)</strong><?php endif; ?>
                </p>
                <p class="hinweis-klein">Aufheben macht das Geschenk wieder zu einer offenen Idee.</p>

                <button type="submit" name="aktion" value="offen_setzen" class="zweitrangig-button">Fest-Zuordnung aufheben</button>
            <?php else: ?>
                <p class="hinweis-klein">Macht die Idee zum Geschenk für genau einen kommenden Anlass. Sie steht dann auf der Personenseite unter „Festgelegte Geschenke“.</p>

                <label for="geschenk_anlass_id">Anlass:</label>
                <select id="geschenk_anlass_id" name="geschenk_anlass_id">
                    <option value="">Anlass auswählen</option>
                    <option value="geburtstag">Geburtstag<?= $aktuellePerson !== null ? ' von ' . htmlspecialchars($aktuellePerson['name']) : '' ?></option>
                    <?php foreach ($gueltigeFestAnlaesse as $anlass): ?>
                        <option value="<?= (int) $anlass['id'] ?>"><?= htmlspecialchars($anlass['name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" name="aktion" value="fest_machen">Als Geschenk festlegen</button>
            <?php endif; ?>

        </section>

        <?php if (!$festIstVergangen): ?>

            <section class="ideen-formular">

                <h2>Als vergangenes Geschenk dokumentieren</h2>

                <p class="hinweis-klein">Für Geschenke, die schon überreicht wurden. Die Idee steht dann auf der Personenseite unter „Vergangene Geschenke“.</p>

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

                <button type="submit" name="aktion" value="vergangen_machen">Als vergangenes Geschenk speichern</button>

            </section>

        <?php endif; ?>

        <section class="ideen-formular kasten-gefahr">

            <h2>Idee löschen</h2>

            <p class="hinweis-klein">Entfernt die Idee dauerhaft. Vorher kommt eine Sicherheitsabfrage.</p>

            <button type="button" popovertarget="idee-loeschen-bestaetigen" class="gefahr-button">Idee löschen</button>

        </section>

    </form>

    <?php /* Eigenes Formular, weil Formulare nicht verschachtelt sein duerfen. */ ?>
    <div id="idee-loeschen-bestaetigen" popover class="bestaetigungs-fenster">
        <div class="fenster-kopf">
            <h2>Idee wirklich löschen?</h2>
            <button class="schliessen"
                    popovertarget="idee-loeschen-bestaetigen"
                    popovertargetaction="hide">
                ×
            </button>
        </div>

        <p>Das kann nicht rückgängig gemacht werden.</p>

        <form method="post" class="fenster-buttons">
            <input type="hidden" name="id" value="<?= (int) $id ?>">
            <button type="button" popovertarget="idee-loeschen-bestaetigen" popovertargetaction="hide">Abbrechen</button>
            <button type="submit" name="aktion" value="loeschen" class="gefahr-button">Ja, endgültig löschen</button>
        </form>
    </div>

</body>

</html>
