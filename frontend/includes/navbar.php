<?php

require_once __DIR__ . '/../../backend/models/Anlass.php';
require_once __DIR__ . '/../../backend/models/Einstellung.php';
require_once __DIR__ . '/../../backend/models/Geschenkidee.php';
require_once __DIR__ . '/../../backend/models/Person.php';
require_once __DIR__ . '/../../backend/models/Ruecksprung.php';

$navBenachrichtigungTage = Einstellung::benachrichtigungTage();
$navHeute = new DateTimeImmutable('today');

$navBenachrichtigungen = [];

// Geburtstage bewusst nicht hier, sondern gesammelt im eigenen Abschnitt weiter unten (mit
// bekannten Geschenkideen) - der deckt ueber Anlass::personenMitAnstehendemGeburtstag() auch
// dasselbe Erinnerungsfenster ab, damit nichts doppelt oder gar nicht erscheint.
foreach (Anlass::alleInklGeburtstage() as $navAnlass) {
    if ($navAnlass['ist_geburtstag']) {
        continue;
    }

    $navDatum = Anlass::naechstesVorkommen($navAnlass);
    $navTage = (int) $navHeute->diff($navDatum)->format('%r%a');

    if ($navTage < 0 || $navTage > $navBenachrichtigungTage) {
        continue;
    }

    $navPersonenNamen = array_column(
        Anlass::personen((int) $navAnlass['id']),
        'name'
    );

    $navBenachrichtigungen[] = [
        'name' => $navAnlass['name'],
        'person' => !empty($navPersonenNamen)
            ? implode(', ', $navPersonenNamen)
            : null,
        'tage' => $navTage,
        'datum' => $navDatum
    ];
}

usort(
    $navBenachrichtigungen,
    fn($a, $b) => $a['datum'] <=> $b['datum']
);

// Weihnachtsstatus - Fortschritt pro Person fuer den geschuetzten Anlass (aktuell nur
// Weihnachten, siehe Anlass::geschuetzte()). Ueber den geschuetzt-Flag statt Namensvergleich
// gefunden, damit eine spaetere Umbenennung die Erkennung nicht bricht. Die eigentliche
// Gruppierung steckt in Geschenkidee::zusammenfassungFuerPflichtanlass() (testbar, siehe
// GeschenkideeTest), damit hier im View keine ungetestete Fachlogik liegt. Bewusst EINE
// Zusammenfassungszeile statt eines Eintrags pro Person - bei vielen Personen wuerde die
// Glocke sonst von Weihnachten geflutet, Details nur aufklappbar (siehe unten).
$navWeihnachtsStatus = null;
$navWeihnachten = Anlass::geschuetzte()[0] ?? null;

if ($navWeihnachten !== null) {
    $navWeihnachtenDatum = Anlass::naechstesVorkommen($navWeihnachten);
    $navWeihnachtenTage = (int) $navHeute->diff($navWeihnachtenDatum)->format('%r%a');

    if ($navWeihnachtenTage >= 0 && $navWeihnachtenTage <= $navBenachrichtigungTage) {
        $navZusammenfassung = Geschenkidee::zusammenfassungFuerPflichtanlass(
            (int) $navWeihnachten['id'],
            $navHeute
        );

        // Ohne angelegte Personen gibt es nichts zusammenzufassen.
        if (array_merge(...array_values($navZusammenfassung)) !== []) {
            $navWeihnachtsStatus = $navZusammenfassung;
        }
    }
}

// Anstehende Geburtstage inkl. bereits bekannter Geschenkideen - innerhalb der "Tage vorher"-
// Erinnerung oder im naechsten Kalendermonat, siehe Anlass::personenMitAnstehendemGeburtstag().
// Bereits nach Datum sortiert, daher hier kein eigenes usort() noetig.
$navGeburtstagsVorschau = [];

foreach (Anlass::personenMitAnstehendemGeburtstag($navBenachrichtigungTage, $navHeute) as $navPerson) {
    $navGeburtstagsIdeen = array_map(
        fn(array $navIdee) => !empty($navIdee['text'])
            ? $navIdee['text']
            : 'Idee ohne Textbeschreibung',
        Geschenkidee::bekannteIdeenFuerGeburtstag((int) $navPerson['id'], $navHeute)
    );

    $navGeburtstagsVorschau[] = [
        'person' => $navPerson['name'],
        'datum' => Anlass::naechstesVorkommen(
            Person::geburtstagAlsAnlass($navPerson),
            $navHeute
        ),
        'ideen' => $navGeburtstagsIdeen
    ];
}

$navBenachrichtigungsAnzahl =
    count($navBenachrichtigungen)
    + count($navGeburtstagsVorschau)
    + ($navWeihnachtsStatus !== null ? 1 : 0);

?>

<nav class="navbar">

    <div class="navbar-logo">
        <span class="logo-icon">GM</span>
        <span class="logo-text">Geschenke-Manager</span>
    </div>

    <div class="navbar-right">

        <?php /* $navZurueck setzt die einbindende Seite vor dem include (auf Bearbeiten-Seiten
                 ueber Ruecksprung::ausAnfrage()) - ein echter Link statt history.back(), damit
                 der Button auch nach Direktaufrufen und Zwischen-Redirects verlaesslich ist. */ ?>
        <?php if (isset($navZurueck)): ?>
            <a href="<?= htmlspecialchars($navZurueck) ?>" class="back-button">← Zurück</a>
        <?php endif; ?>

        <button class="glocke" popovertarget="benachrichtigungen">
            🔔

            <?php if ($navBenachrichtigungsAnzahl > 0): ?>
                <span class="benachrichtigungs-anzahl">
                    <?= $navBenachrichtigungsAnzahl ?>
                </span>
            <?php endif; ?>
        </button>

        <a href="index.php" class="home-button">Home</a>

    </div>

</nav>


<div id="benachrichtigungen"
     popover
     class="benachrichtigungs-fenster">

    <div class="fenster-kopf">

        <h2>Benachrichtigungen</h2>

        <div class="fenster-buttons">

            <button class="einstellungen-button"
                    popovertarget="benachrichtigungs-einstellungen">
                ⚙
            </button>

            <button class="schliessen"
                    popovertarget="benachrichtigungen"
                    popovertargetaction="hide">
                ×
            </button>

        </div>

    </div>


    <?php if ($navBenachrichtigungsAnzahl === 0): ?>

        <p>Keine anstehenden Benachrichtigungen.</p>

    <?php else: ?>


        <?php if (!empty($navBenachrichtigungen)): ?>

            <h3>Anstehende Anlässe</h3>

            <?php foreach ($navBenachrichtigungen as $navBenachrichtigung): ?>

                <p class="benachrichtigungs-eintrag">

                    <?php if ($navBenachrichtigung['tage'] === 0): ?>
                        Heute ist
                    <?php elseif ($navBenachrichtigung['tage'] === 1): ?>
                        Morgen ist
                    <?php else: ?>
                        In <?= $navBenachrichtigung['tage'] ?> Tagen ist
                    <?php endif; ?>

                    <strong><?= htmlspecialchars($navBenachrichtigung['name']) ?></strong>

                    <?php if (!empty($navBenachrichtigung['person'])): ?>
                        von <?= htmlspecialchars($navBenachrichtigung['person']) ?>
                    <?php endif; ?>.

                </p>

            <?php endforeach; ?>

        <?php endif; ?>


        <?php if (!empty($navGeburtstagsVorschau)): ?>

            <h3>Geburtstage</h3>

            <?php foreach ($navGeburtstagsVorschau as $navVorschau): ?>

                <?php
                $navGeburtstagsTage = (int) $navHeute
                    ->diff($navVorschau['datum'])
                    ->format('%r%a');
                ?>

                <p class="benachrichtigungs-eintrag">

                    <strong><?= htmlspecialchars($navVorschau['person']) ?></strong>
                    – <?= htmlspecialchars($navVorschau['datum']->format('d.m.Y')) ?>

                    <br>

                    <?php if ($navGeburtstagsTage === 0): ?>
                        Heute
                    <?php elseif ($navGeburtstagsTage === 1): ?>
                        Morgen
                    <?php else: ?>
                        In <?= $navGeburtstagsTage ?> Tagen
                    <?php endif; ?>

                    <br>

                    <?php if (!empty($navVorschau['ideen'])): ?>
                        Geschenke und Ideen:
                        <?= htmlspecialchars(implode(', ', $navVorschau['ideen'])) ?>
                    <?php else: ?>
                        Noch keine Geschenkidee bekannt.
                    <?php endif; ?>

                </p>

            <?php endforeach; ?>

        <?php endif; ?>


        <?php if ($navWeihnachtsStatus !== null): ?>

            <h3>Weihnachtsstatus</h3>

            <?php /* Kein eigener Countdown - Weihnachten steht innerhalb derselben Frist bereits
                     unter "Anstehende Anlässe". */ ?>
            <p class="benachrichtigungs-eintrag">
                <strong><?= count($navWeihnachtsStatus['besorgt']) ?></strong> besorgt ·
                <strong><?= count($navWeihnachtsStatus['offen']) ?></strong> offen ·
                <strong><?= count($navWeihnachtsStatus['ohne_idee']) ?></strong> ohne Idee
            </p>

            <?php if ($navWeihnachtsStatus['offen'] === [] && $navWeihnachtsStatus['ohne_idee'] === []): ?>

                <p class="benachrichtigungs-eintrag">Für alle Personen ist alles besorgt.</p>

            <?php else: ?>

                <details class="weihnachts-details">
                    <summary>Wer fehlt noch?</summary>

                    <?php if ($navWeihnachtsStatus['offen'] !== []): ?>
                        <p>
                            <strong>Offen:</strong>
                            <?= htmlspecialchars(implode(', ', $navWeihnachtsStatus['offen'])) ?>
                        </p>
                    <?php endif; ?>

                    <?php if ($navWeihnachtsStatus['ohne_idee'] !== []): ?>
                        <p>
                            <strong>Ohne Idee:</strong>
                            <?= htmlspecialchars(implode(', ', $navWeihnachtsStatus['ohne_idee'])) ?>
                        </p>
                    <?php endif; ?>
                </details>

            <?php endif; ?>

        <?php endif; ?>


    <?php endif; ?>

</div>


<div id="benachrichtigungs-einstellungen"
     popover
     class="benachrichtigungs-fenster">

    <div class="fenster-kopf">

        <h2>Einstellungen</h2>

        <button class="schliessen"
                popovertarget="benachrichtigungs-einstellungen"
                popovertargetaction="hide">
            ×
        </button>

    </div>

    <?php /* Eigener Endpunkt statt POST an die aktuelle Seite, siehe einstellung-speichern.php. */ ?>
    <form method="post" action="einstellung-speichern.php">

        <input type="hidden"
               name="zurueck"
               value="<?= htmlspecialchars(Ruecksprung::aktuelleSeite($_SERVER['REQUEST_URI'] ?? '', 'index.php')) ?>">

        <label for="benachrichtigung_tage">
            Wie viele Tage vorher möchtest du erinnert werden?
        </label>

        <select id="benachrichtigung_tage"
                name="benachrichtigung_tage">

            <option value="1"
                <?= $navBenachrichtigungTage === 1 ? 'selected' : '' ?>>
                1 Tag
            </option>

            <option value="3"
                <?= $navBenachrichtigungTage === 3 ? 'selected' : '' ?>>
                3 Tage
            </option>

            <option value="7"
                <?= $navBenachrichtigungTage === 7 ? 'selected' : '' ?>>
                7 Tage
            </option>

            <option value="14"
                <?= $navBenachrichtigungTage === 14 ? 'selected' : '' ?>>
                14 Tage
            </option>

            <option value="30"
                <?= $navBenachrichtigungTage === 30 ? 'selected' : '' ?>>
                30 Tage
            </option>

        </select>

        <button type="submit">Speichern</button>

    </form>

</div>

<script>
// Wird eine Seite ueber die Browser-Zuruecktaste aus dem Back-Forward-Cache wiederhergestellt,
// zeigt sie sonst den alten Stand (z. B. eine Idee, die man gerade bearbeitet hat). Bewusst nur
// bei event.persisted neu laden: eine per Zuruecktaste frisch geladene Seite ist ohnehin aktuell,
// ein zusaetzlicher Reload wuerde sie nur doppelt laden.
window.addEventListener('pageshow', function (event) {
    if (event.persisted) {
        window.location.reload();
    }
});
</script>
