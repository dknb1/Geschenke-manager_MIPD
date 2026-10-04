<?php

require_once __DIR__ . '/../../backend/models/Anlass.php';
require_once __DIR__ . '/../../backend/models/Einstellung.php';
require_once __DIR__ . '/../../backend/models/Geschenkidee.php';
require_once __DIR__ . '/../../backend/models/Person.php';
require_once __DIR__ . '/../../backend/models/Ruecksprung.php';

$navBenachrichtigungTage = Einstellung::benachrichtigungTage();
$navHeute = new DateTimeImmutable('today');

$navBenachrichtigungen = [];

// Geburtstage stehen in einem eigenen Abschnitt weiter unten.
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

// Weihnachtsstatus als eine Zusammenfassung statt einer Zeile pro Person.
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

        if (array_merge(...array_values($navZusammenfassung)) !== []) {
            $navWeihnachtsStatus = $navZusammenfassung;
        }
    }
}

// Geburtstage in der Frist (zaehlen an der Glocke) und Vorschau auf den naechsten Monat (zaehlt nicht).
$navGeburtstagsEintraege = fn(array $navPersonen) => array_map(
    fn(array $navPerson) => [
        'person' => $navPerson['name'],
        'datum' => Anlass::naechstesVorkommen(
            Person::geburtstagAlsAnlass($navPerson),
            $navHeute
        ),
        'ideen' => array_map(
            fn(array $navIdee) => !empty($navIdee['text'])
                ? $navIdee['text']
                : 'Idee ohne Textbeschreibung',
            Geschenkidee::bekannteIdeenFuerGeburtstag((int) $navPerson['id'], $navHeute)
        )
    ],
    $navPersonen
);

$navGeburtstageInFrist = $navGeburtstagsEintraege(
    Anlass::personenMitGeburtstagInFrist($navBenachrichtigungTage, $navHeute)
);
$navGeburtstagsVorschau = $navGeburtstagsEintraege(
    Anlass::geburtstagsVorschauNaechsterMonat($navBenachrichtigungTage, $navHeute)
);

$navNaechsterMonatName = Anlass::MONATSNAMEN[(int) $navHeute->modify('first day of next month')->format('n')];

$navGeburtstagsAbschnitte = [
    'Geburtstage' => $navGeburtstageInFrist,
    'Vorschau: Geburtstage im ' . $navNaechsterMonatName => $navGeburtstagsVorschau
];

$navBenachrichtigungsAnzahl =
    count($navBenachrichtigungen)
    + count($navGeburtstageInFrist)
    + ($navWeihnachtsStatus !== null ? 1 : 0);

?>

<nav class="navbar">

    <div class="navbar-logo">
        <span class="logo-text">Geschenke-Manager</span>
    </div>

    <div class="navbar-right">

        <?php /* $navZurueck setzt die Seite vor dem Einbinden. */ ?>
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

    <?php endif; ?>


    <?php if (!empty($navBenachrichtigungen)): ?>

        <h3>Anstehende Anlässe</h3>

        <?php foreach ($navBenachrichtigungen as $navBenachrichtigung): ?>

            <?php
            $navEinleitung = match ($navBenachrichtigung['tage']) {
                0 => 'Heute ist',
                1 => 'Morgen ist',
                default => 'In ' . $navBenachrichtigung['tage'] . ' Tagen ist',
            };
            ?>

            <?php /* In einer Zeile, sonst entsteht ein Leerzeichen vor dem Punkt. */ ?>
            <p class="benachrichtigungs-eintrag">
                <?= $navEinleitung ?> <strong><?= htmlspecialchars($navBenachrichtigung['name']) ?></strong><?php if (!empty($navBenachrichtigung['person'])): ?> von <?= htmlspecialchars($navBenachrichtigung['person']) ?><?php endif; ?>.
            </p>

        <?php endforeach; ?>

    <?php endif; ?>

    <?php if ($navWeihnachtsStatus !== null): ?>

        <h3>Weihnachtsstatus</h3>

        <?php /* Weihnachten selbst steht schon unter "Anstehende Anlässe". */ ?>
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

    <?php foreach ($navGeburtstagsAbschnitte as $navAbschnittTitel => $navAbschnittEintraege): ?>
    <?php if (!empty($navAbschnittEintraege)): ?>

        <h3><?= htmlspecialchars($navAbschnittTitel) ?></h3>

        <?php foreach ($navAbschnittEintraege as $navVorschau): ?>

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
    <?php endforeach; ?>


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

    <?php /* Eigene Seite zum Speichern, siehe einstellung-speichern.php. */ ?>
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
