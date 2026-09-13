<?php

require_once __DIR__ . '/../../backend/models/Anlass.php';
require_once __DIR__ . '/../../backend/models/Einstellung.php';
require_once __DIR__ . '/../../backend/models/Geschenkidee.php';
require_once __DIR__ . '/../../backend/models/Person.php';

// Neue Einstellung speichern
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['benachrichtigung_tage'])) {

    $navNeueTage = filter_input(
        INPUT_POST,
        'benachrichtigung_tage',
        FILTER_VALIDATE_INT
    );

    if ($navNeueTage !== false && $navNeueTage >= 0 && $navNeueTage <= 365) {
        Einstellung::benachrichtigungTageSpeichern($navNeueTage);
    }
}

// Aktuell gespeicherte Einstellung laden
$navBenachrichtigungTage = Einstellung::benachrichtigungTage();

// Normale Anlass-Benachrichtigungen
$navAnlaesse = Anlass::alleInklGeburtstage();
$navBenachrichtigungen = [];
$navHeute = new DateTimeImmutable('today');

foreach ($navAnlaesse as $navAnlass) {
    $navNaechstesDatum = Anlass::naechstesVorkommen($navAnlass);
    $navTage = (int) $navHeute->diff($navNaechstesDatum)->format('%r%a');

    if ($navTage >= 0 && $navTage <= $navBenachrichtigungTage) {
        $navPersonName = null;

        if (!$navAnlass['ist_geburtstag']) {
            $navPersonenNamen = array_column(
                Anlass::personen((int) $navAnlass['id']),
                'name'
            );

            $navPersonName = !empty($navPersonenNamen)
                ? implode(', ', $navPersonenNamen)
                : null;
        }

        $navBenachrichtigungen[] = [
            'name' => $navAnlass['name'],
            'person' => $navPersonName,
            'tage' => $navTage,
            'datum' => $navNaechstesDatum
        ];
    }
}

usort($navBenachrichtigungen, function ($a, $b) {
    return $a['datum'] <=> $b['datum'];
});

// Weihnachtsstatus - Fortschritt pro Person fuer den geschuetzten Anlass (aktuell nur
// Weihnachten, siehe Anlass::geschuetzte()). Ueber den geschuetzt-Flag statt Namensvergleich
// gefunden, damit eine spaetere Umbenennung die Erkennung nicht bricht. Die eigentliche
// Gruppierung steckt in Geschenkidee::statusNachPersonFuerAnlass() (testbar, siehe
// GeschenkideeTest), damit hier im View keine ungetestete Fachlogik liegt.
$navWeihnachtsStatus = [];
$navWeihnachten = Anlass::geschuetzte()[0] ?? null;

if ($navWeihnachten !== null) {
    $navWeihnachtenDatum = Anlass::naechstesVorkommen($navWeihnachten);
    $navWeihnachtenTage = (int) $navHeute->diff($navWeihnachtenDatum)->format('%r%a');

    if ($navWeihnachtenTage >= 0 && $navWeihnachtenTage <= $navBenachrichtigungTage) {
        $navWeihnachtsStatus = Geschenkidee::statusNachPersonFuerAnlass((int) $navWeihnachten['id'], $navHeute);
    }
}

// Geburtstage im naechsten Monat inkl. bereits bekannter Geschenkideen (unabhaengig von der
// einstellbaren "Tage vorher"-Erinnerung oben, siehe Anlass::personenMitGeburtstagImNaechstenMonat())
$navGeburtstagsVorschau = [];

foreach (Anlass::personenMitGeburtstagImNaechstenMonat($navHeute) as $navPerson) {
    $navGeburtstagsIdeen = array_map(
        function (array $navIdee) {
            return !empty($navIdee['text']) ? $navIdee['text'] : 'Idee ohne Textbeschreibung';
        },
        Geschenkidee::bekannteIdeenFuerGeburtstag((int) $navPerson['id'], $navHeute)
    );

    $navGeburtstagsVorschau[] = [
        'person' => $navPerson['name'],
        'datum' => Anlass::naechstesVorkommen(Person::geburtstagAlsAnlass($navPerson), $navHeute),
        'ideen' => $navGeburtstagsIdeen,
    ];
}

usort($navGeburtstagsVorschau, function ($a, $b) {
    return $a['datum'] <=> $b['datum'];
});

$navBenachrichtigungsAnzahl =
    count($navBenachrichtigungen)
    + (!empty($navWeihnachtsStatus) ? 1 : 0)
    + count($navGeburtstagsVorschau);

?>

<nav class="navbar">

    <div class="navbar-logo">
        <span class="logo-icon">GM</span>
        <span class="logo-text">Geschenke-Manager</span>
    </div>

    <div class="navbar-right">

        <button class="glocke" popovertarget="benachrichtigungen">

            🔔

            <?php if ($navBenachrichtigungsAnzahl > 0): ?>
                <span class="benachrichtigungs-anzahl">
                    <?= $navBenachrichtigungsAnzahl ?>
                </span>
            <?php endif; ?>

        </button>

        <a href="index.php" class="home-button">
            Home
        </a>

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

    <?php if (empty($navBenachrichtigungen) && empty($navWeihnachtsStatus) && empty($navGeburtstagsVorschau)): ?>

        <p>Keine anstehenden Benachrichtigungen.</p>

    <?php else: ?>

        <?php foreach ($navBenachrichtigungen as $navBenachrichtigung): ?>

            <p>

                <?php if ($navBenachrichtigung['tage'] === 0): ?>

                    Heute ist
                    <?= htmlspecialchars($navBenachrichtigung['name']) ?>

                <?php elseif ($navBenachrichtigung['tage'] === 1): ?>

                    Morgen ist
                    <?= htmlspecialchars($navBenachrichtigung['name']) ?>

                <?php else: ?>

                    In <?= $navBenachrichtigung['tage'] ?> Tagen ist
                    <?= htmlspecialchars($navBenachrichtigung['name']) ?>

                <?php endif; ?>

                <?php if (!empty($navBenachrichtigung['person'])): ?>

                    von
                    <?= htmlspecialchars($navBenachrichtigung['person']) ?>

                <?php endif; ?>.

            </p>

        <?php endforeach; ?>

        <?php if (!empty($navWeihnachtsStatus)): ?>

            <h3>Weihnachtsstatus</h3>

            <?php foreach ($navWeihnachtsStatus as $navStatus): ?>

                <?php $navStatusText = $navStatus['besorgt'] === $navStatus['ideen'] ? 'Besorgt' : 'Offen'; ?>

                <p>
                    <strong><?= htmlspecialchars($navStatus['person']) ?>:</strong>
                    Status: <strong><?= $navStatusText ?></strong>
                </p>

            <?php endforeach; ?>

        <?php endif; ?>

        <?php if (!empty($navGeburtstagsVorschau)): ?>

            <h3>Geburtstage im nächsten Monat</h3>

            <?php foreach ($navGeburtstagsVorschau as $navVorschau): ?>

                <p>
                    <strong><?= htmlspecialchars($navVorschau['person']) ?>:</strong>
                    <?= htmlspecialchars($navVorschau['datum']->format('d.m.Y')) ?>
                    <br>

                    <?php if (!empty($navVorschau['ideen'])): ?>
                        Bereits bekannte Geschenkideen:
                        <?= htmlspecialchars(implode(', ', $navVorschau['ideen'])) ?>
                    <?php else: ?>
                        Noch keine Geschenkidee bekannt.
                    <?php endif; ?>
                </p>

            <?php endforeach; ?>

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

    <form method="post">

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

        <button type="submit">
            Speichern
        </button>

    </form>

</div>
