<?php

require_once __DIR__ . '/../../backend/models/Anlass.php';
require_once __DIR__ . '/../../backend/models/Einstellung.php';
require_once __DIR__ . '/../../backend/models/Geschenkidee.php';
require_once __DIR__ . '/../../backend/models/Person.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['benachrichtigung_tage'])) {
    $navNeueTage = filter_input(INPUT_POST, 'benachrichtigung_tage', FILTER_VALIDATE_INT);

    if ($navNeueTage !== false && $navNeueTage >= 0 && $navNeueTage <= 365) {
        Einstellung::benachrichtigungTageSpeichern($navNeueTage);
    }
}

$navBenachrichtigungTage = Einstellung::benachrichtigungTage();
$navHeute = new DateTimeImmutable('today');

$navBenachrichtigungen = [];

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

$navWeihnachtsStatus = [];
$navWeihnachten = Anlass::geschuetzte()[0] ?? null;

if ($navWeihnachten !== null) {
    $navWeihnachtenDatum = Anlass::naechstesVorkommen($navWeihnachten);
    $navWeihnachtenTage = (int) $navHeute->diff($navWeihnachtenDatum)->format('%r%a');

    if ($navWeihnachtenTage >= 0 && $navWeihnachtenTage <= $navBenachrichtigungTage) {
        $navWeihnachtsStatus = Geschenkidee::statusNachPersonFuerAnlass(
            (int) $navWeihnachten['id'],
            $navHeute
        );
    }
}

$navGeburtstagsVorschau = [];

foreach (Anlass::personenMitGeburtstagImNaechstenMonat($navHeute) as $navPerson) {
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

usort(
    $navGeburtstagsVorschau,
    fn($a, $b) => $a['datum'] <=> $b['datum']
);

$navBenachrichtigungsAnzahl =
    count($navBenachrichtigungen)
    + count($navGeburtstagsVorschau)
    + (!empty($navWeihnachtsStatus) ? 1 : 0);

?>

<nav class="navbar">

    <div class="navbar-logo">
        <span class="logo-icon">GM</span>
        <span class="logo-text">Geschenke-Manager</span>
    </div>

    <div class="navbar-right">

        <?php if (basename($_SERVER['PHP_SELF'] ?? '') !== 'index.php'): ?>
            <button type="button" class="back-button" onclick="window.history.back()">
                ← Zurück
            </button>
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
                        Geschenkideen:
                        <?= htmlspecialchars(implode(', ', $navVorschau['ideen'])) ?>
                    <?php else: ?>
                        Noch keine Geschenkidee bekannt.
                    <?php endif; ?>

                </p>

            <?php endforeach; ?>

        <?php endif; ?>


        <?php if (!empty($navWeihnachtsStatus)): ?>

            <h3>Weihnachtsstatus</h3>

            <?php foreach ($navWeihnachtsStatus as $navStatus): ?>

                <?php
                $navStatusText = $navStatus['besorgt'] === $navStatus['ideen']
                    ? 'Besorgt'
                    : 'Offen';
                ?>

                <p class="benachrichtigungs-eintrag">
                    <strong><?= htmlspecialchars($navStatus['person']) ?>:</strong>
                    <?= $navStatusText ?>
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

        <button type="submit">Speichern</button>

    </form>

</div>

<script>
window.addEventListener('pageshow', function (event) {
    const navigation = performance.getEntriesByType('navigation')[0];

    if (event.persisted || (navigation && navigation.type === 'back_forward')) {
        window.location.reload();
    }
});
</script>