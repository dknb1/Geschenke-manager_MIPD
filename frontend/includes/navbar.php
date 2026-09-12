<?php

require_once __DIR__ . '/../../backend/models/Anlass.php';
require_once __DIR__ . '/../../backend/models/Einstellung.php';
require_once __DIR__ . '/../../backend/models/Geschenkidee.php';

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

// Weihnachtsstatus
$navWeihnachtsStatus = [];
$navWeihnachten = null;

// Nur zum Testen auf true.
// Nach erfolgreichem Test wieder auf false setzen.
$navWeihnachtsTestmodus = false;

foreach (Anlass::alle() as $navMoeglicherAnlass) {
    if (strcasecmp(trim($navMoeglicherAnlass['name']), 'Weihnachten') === 0) {
        $navWeihnachten = $navMoeglicherAnlass;
        break;
    }
}

if ($navWeihnachten !== null) {
    $navWeihnachtenDatum = Anlass::naechstesVorkommen($navWeihnachten);
    $navWeihnachtenTage = (int) $navHeute->diff($navWeihnachtenDatum)->format('%r%a');

    if ($navWeihnachtsTestmodus
        || ($navWeihnachtenTage >= 0
            && $navWeihnachtenTage <= $navBenachrichtigungTage)) {

        $navWeihnachtsStatusNachPerson = [];

        foreach (Geschenkidee::alle() as $navIdee) {
            $navIstFest = Geschenkidee::istFest($navIdee);

            if ($navIstFest) {
                $navIstFuerWeihnachten =
                    (int) ($navIdee['geschenk_anlass_id'] ?? 0)
                    === (int) $navWeihnachten['id'];
            } else {
                $navLoseAnlassIds = array_map(
                    'intval',
                    array_column(
                        Geschenkidee::anlaesse((int) $navIdee['id']),
                        'id'
                    )
                );

                $navIstFuerWeihnachten = in_array(
                    (int) $navWeihnachten['id'],
                    $navLoseAnlassIds,
                    true
                );
            }

            if (!$navIstFuerWeihnachten) {
                continue;
            }

            $navIstVergangen = $navIstFest
                && !empty($navIdee['geschenk_datum'])
                && new DateTimeImmutable($navIdee['geschenk_datum']) < $navHeute;

            if ($navIstVergangen) {
                continue;
            }

            $navPersonId = (int) $navIdee['person_id'];

            if (!isset($navWeihnachtsStatusNachPerson[$navPersonId])) {
                $navWeihnachtsStatusNachPerson[$navPersonId] = [
                    'person' => $navIdee['person_name'],
                    'ideen' => 0,
                    'besorgt' => 0,
                    'offene_aufgaben' => []
                ];
            }

            $navWeihnachtsStatusNachPerson[$navPersonId]['ideen']++;

            if ((int) ($navIdee['besorgt'] ?? 0) === 1) {
                $navWeihnachtsStatusNachPerson[$navPersonId]['besorgt']++;
            }

            if (!empty($navIdee['offene_aufgaben'])) {
                $navWeihnachtsStatusNachPerson[$navPersonId]['offene_aufgaben'][] =
                    trim($navIdee['offene_aufgaben']);
            }
        }

        $navWeihnachtsStatus = array_values($navWeihnachtsStatusNachPerson);
    }
}

$navBenachrichtigungsAnzahl =
    count($navBenachrichtigungen)
    + (!empty($navWeihnachtsStatus) ? 1 : 0);

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

    <?php if (empty($navBenachrichtigungen) && empty($navWeihnachtsStatus)): ?>

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

                <p>
    <strong><?= htmlspecialchars($navStatus['person']) ?>:</strong>

    <?php
    $navStatusText = $navStatus['besorgt'] === $navStatus['ideen']
        ? 'Besorgt'
        : 'Offen';
    ?>

    Status: <strong><?= $navStatusText ?></strong>
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