<?php

require_once __DIR__ . '/../../backend/models/Anlass.php';
require_once __DIR__ . '/../../backend/models/Einstellung.php';

// Neue Einstellung speichern
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['benachrichtigung_tage'])) {

    $neueTage = filter_input(
        INPUT_POST,
        'benachrichtigung_tage',
        FILTER_VALIDATE_INT
    );

    if ($neueTage !== false && $neueTage >= 0 && $neueTage <= 365) {
        Einstellung::benachrichtigungTageSpeichern($neueTage);
    }
}

// Aktuell gespeicherte Einstellung laden
$benachrichtigungTage = Einstellung::benachrichtigungTage();

$anlaesse = Anlass::alle();
$benachrichtigungen = [];

$heute = new DateTimeImmutable('today');

foreach ($anlaesse as $anlass) {

    $naechstesDatum = Anlass::naechstesVorkommen($anlass);

    $tage = (int) $heute->diff($naechstesDatum)->format('%r%a');

    if ($tage >= 0 && $tage <= $benachrichtigungTage) {

        $benachrichtigungen[] = [
            'name' => $anlass['name'],
            'person' => $anlass['person_name'] ?? null,
            'tage' => $tage,
            'datum' => $naechstesDatum
        ];
    }
}

usort($benachrichtigungen, function ($a, $b) {
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

    <?php if (count($benachrichtigungen) > 0): ?>
        <span class="benachrichtigungs-anzahl">
            <?= count($benachrichtigungen) ?>
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


    <?php if (empty($benachrichtigungen)): ?>

        <p>Keine anstehenden Anlässe.</p>

    <?php else: ?>

        <?php foreach ($benachrichtigungen as $benachrichtigung): ?>

            <p>

                <?php if ($benachrichtigung['tage'] === 0): ?>

                    Heute ist
                    <?= htmlspecialchars($benachrichtigung['name']) ?>

                <?php elseif ($benachrichtigung['tage'] === 1): ?>

                    Morgen ist
                    <?= htmlspecialchars($benachrichtigung['name']) ?>

                <?php else: ?>

                    In <?= $benachrichtigung['tage'] ?> Tagen ist
                    <?= htmlspecialchars($benachrichtigung['name']) ?>

                <?php endif; ?>

                <?php if (!empty($benachrichtigung['person'])): ?>

                    von
                    <?= htmlspecialchars($benachrichtigung['person']) ?>

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
                <?= $benachrichtigungTage === 1 ? 'selected' : '' ?>>
                1 Tag
            </option>

            <option value="3"
                <?= $benachrichtigungTage === 3 ? 'selected' : '' ?>>
                3 Tage
            </option>

            <option value="7"
                <?= $benachrichtigungTage === 7 ? 'selected' : '' ?>>
                7 Tage
            </option>

            <option value="14"
                <?= $benachrichtigungTage === 14 ? 'selected' : '' ?>>
                14 Tage
            </option>

            <option value="30"
                <?= $benachrichtigungTage === 30 ? 'selected' : '' ?>>
                30 Tage
            </option>

        </select>

        <button type="submit">
            Speichern
        </button>

    </form>

</div>