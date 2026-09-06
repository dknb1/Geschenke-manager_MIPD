<?php

require_once __DIR__ . '/../../backend/models/Anlass.php';
require_once __DIR__ . '/../../backend/models/Einstellung.php';

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

// Alle Variablen hier bewusst mit "nav"-Praefix: navbar.php wird per include() eingebunden
// und teilt sich dadurch den Variablen-Scope mit der jeweils einbindenden Seite - ohne
// Praefix wuerden generische Namen wie $anlaesse/$anlass/$heute die gleichnamigen Variablen
// der einbindenden Seite ueberschreiben (siehe z. B. anlaesse.php, das selbst $anlaesse/
// $anlass fuer seine eigene Liste verwendet).
// Geburtstage sind fachlich Anlaesse, stehen aber bewusst nicht als eigene Zeile in
// anlaesse (siehe Person::geburtstagAlsAnlass()) - Anlass::alleInklGeburtstage() blendet sie
// genau wie in anlaesse.php zusaetzlich ein. Ohne das wuerden Geburtstage nie als
// Benachrichtigung auftauchen, obwohl das fachlich ausdruecklich gefordert ist
// ("Benachrichtigung ueber Geburtstage im naechsten Monat").
$navAnlaesse = Anlass::alleInklGeburtstage();
$navBenachrichtigungen = [];

$navHeute = new DateTimeImmutable('today');

foreach ($navAnlaesse as $navAnlass) {

    $navNaechstesDatum = Anlass::naechstesVorkommen($navAnlass);

    $navTage = (int) $navHeute->diff($navNaechstesDatum)->format('%r%a');

    if ($navTage >= 0 && $navTage <= $navBenachrichtigungTage) {

        // anlaesse.person_name (freier Text) gibt es nicht mehr - Personen sind jetzt per
        // N:M (anlass_personen) verknuepft, ein Anlass kann also auch mehreren Personen
        // gehoeren (siehe Anlass::personen()). Bei Geburtstagen steckt der Personenname
        // bereits im Anlassnamen ("Geburtstag Julia") - keine zusaetzliche Personen-Zeile
        // noetig, sonst wuerde "von Julia" redundant nochmal angehaengt.
        $navPersonName = null;
        if (!$navAnlass['ist_geburtstag']) {
            $navPersonenNamen = array_column(Anlass::personen((int) $navAnlass['id']), 'name');
            $navPersonName = !empty($navPersonenNamen) ? implode(', ', $navPersonenNamen) : null;
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

?>


<nav class="navbar">

    <div class="navbar-logo">
        <span class="logo-icon">GM</span>
        <span class="logo-text">Geschenke-Manager</span>
    </div>

    <div class="navbar-right">

  <button class="glocke"
        popovertarget="benachrichtigungen">

    🔔

    <?php if (count($navBenachrichtigungen) > 0): ?>
        <span class="benachrichtigungs-anzahl">
            <?= count($navBenachrichtigungen) ?>
        </span>
    <?php endif; ?>

</button>

        <a href="index.php"
           class="home-button">
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


    <?php if (empty($navBenachrichtigungen)): ?>

        <p>Keine anstehenden Anlässe.</p>

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
