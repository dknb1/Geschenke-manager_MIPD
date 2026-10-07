<?php
require_once __DIR__ . '/../backend/models/Person.php';
require_once __DIR__ . '/../backend/models/Anlass.php';
require_once __DIR__ . '/../backend/models/Geschenkidee.php';
require_once __DIR__ . '/../backend/models/Ruecksprung.php';

$zurueck = Ruecksprung::ausAnfrage('index.php');

// Alle Personen mit Anlaessen und Geschenken auf einer druckbaren Seite.
$personen = Person::alle();
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Gesamtliste</title>

    <link rel="stylesheet" href="css/style.css">
</head>

<body>

    <?php $navZurueck = $zurueck; include 'includes/navbar.php'; ?>

    <?php /* Ohne aeusseren Rahmen, jede Person ist ein eigener Kasten. */ ?>
    <div>

        <h1>Gesamtliste</h1>

        <button type="button" class="gesamtliste-drucken" onclick="window.print()">Drucken</button>

        <?php if (empty($personen)): ?>

            <p>Es sind noch keine Personen angelegt.</p>

        <?php endif; ?>

        <?php foreach ($personen as $person): ?>

            <?php
            $verknuepfteAnlaesse = Anlass::vonPersonInklGeschuetzte((int) $person['id']);

            $geschenkideenSortiert = Geschenkidee::sortiereNachStatus(
                Geschenkidee::vonPerson((int) $person['id'])
            );
            $offeneGeschenkideen = $geschenkideenSortiert['offen'];
            $festeGeschenke = $geschenkideenSortiert['fest'];
            $vergangeneGeschenke = $geschenkideenSortiert['vergangen'];

            $geburtstagAlsAnlass = Person::geburtstagAlsAnlass($person);
            ?>

            <section class="gesamtliste-person kasten">

                <h2><?= htmlspecialchars($person['name']) ?> (<?= Person::alterAlsText($person) ?>)</h2>

                <?php if (!empty($person['details'])): ?>
                    <h3>Details</h3>

                    <ul class="ideen-liste">
                        <li><?= nl2br(htmlspecialchars($person['details'])) ?></li>
                    </ul>
                <?php endif; ?>

                <h3>Anlässe</h3>

                <?php /* Geburtstag als erster Kasten, gleich aussehend wie die uebrigen Anlaesse. */ ?>
                <ul class="ideen-liste">
                    <li>
                        <?= htmlspecialchars($geburtstagAlsAnlass['name']) ?> -
                        <?= htmlspecialchars(Anlass::naechstesVorkommen($geburtstagAlsAnlass)->format('d.m.Y')) ?>
                        <strong>(Geburtstag)</strong>
                    </li>
                    <?php foreach ($verknuepfteAnlaesse as $verknuepfterAnlass): ?>
                        <li>
                            <?= htmlspecialchars($verknuepfterAnlass['name']) ?> -
                            <?= htmlspecialchars(Anlass::naechstesVorkommen($verknuepfterAnlass)->format('d.m.Y')) ?>
                            <?php if ((int) $verknuepfterAnlass['geschuetzt'] === 1): ?>
                                <strong>(Pflichtanlass)</strong>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <?php /* Geburtstag und Weihnachten hat jede Person, "weitere" meint eigene Anlaesse. */ ?>
                <?php if (Anlass::vonPerson((int) $person['id']) === []): ?>
                    <p>Keine weiteren Anlässe hinterlegt.</p>
                <?php endif; ?>

                <h3>Geschenkideen</h3>

                <?php if (empty($offeneGeschenkideen)): ?>
                    <p>Keine offenen Geschenkideen hinterlegt.</p>
                <?php else: ?>
                    <ul class="ideen-liste">
                        <?php foreach ($offeneGeschenkideen as $idee): ?>
                            <?php $anlassNamen = Geschenkidee::anlassNamenInklGeburtstag((int) $idee['id']); ?>
                            <li>
                                <?php if (!empty($idee['text'])): ?>
                                    <?= htmlspecialchars($idee['text']) ?>
                                <?php endif; ?>
                                <?php if (!empty($anlassNamen)): ?>
                                    (<?= htmlspecialchars(implode(', ', $anlassNamen)) ?>)
                                <?php endif; ?>

                                <p>Besorgt: <strong><?= (int) ($idee['besorgt'] ?? 0) === 1 ? 'Ja' : 'Nein' ?></strong></p>
                                <?php if (!empty($idee['offene_aufgaben'])): ?>
                                    <p>Offene Aufgaben: <?= htmlspecialchars($idee['offene_aufgaben']) ?></p>
                                <?php endif; ?>

                                <?php if (!empty($idee['link']) || !empty($idee['bild_link'])): ?>
                                    <div class="ideen-liste-extras">
                                        <?php if (!empty($idee['link'])): ?>
                                            <a href="<?= htmlspecialchars($idee['link']) ?>" target="_blank" rel="noopener noreferrer">Link</a>
                                        <?php endif; ?>
                                        <?php if (!empty($idee['bild_link'])): ?>
                                            <a href="<?= htmlspecialchars($idee['bild_link']) ?>" target="_blank" rel="noopener noreferrer">Bild</a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <h3>Festgelegte Geschenke</h3>

                <?php if (empty($festeGeschenke)): ?>
                    <p>Keine Geschenke fest zugeordnet.</p>
                <?php else: ?>
                    <ul class="ideen-liste">
                        <?php foreach ($festeGeschenke as $idee): ?>
                            <?php $anlassNamen = Geschenkidee::anlassNamenInklGeburtstag((int) $idee['id']); ?>
                            <li>
                                <?php if (!empty($idee['text'])): ?>
                                    <?= htmlspecialchars($idee['text']) ?>
                                <?php endif; ?>
                                <?php if (!empty($anlassNamen)): ?>
                                    (<?= htmlspecialchars(implode(', ', $anlassNamen)) ?>)
                                <?php endif; ?>

                                <p>Besorgt: <strong><?= (int) ($idee['besorgt'] ?? 0) === 1 ? 'Ja' : 'Nein' ?></strong></p>
                                <?php if (!empty($idee['offene_aufgaben'])): ?>
                                    <p>Offene Aufgaben: <?= htmlspecialchars($idee['offene_aufgaben']) ?></p>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <h3>Vergangene Geschenke</h3>

                <?php if (empty($vergangeneGeschenke)): ?>
                    <p>Keine vergangenen Geschenke dokumentiert.</p>
                <?php else: ?>
                    <ul class="ideen-liste">
                        <?php foreach ($vergangeneGeschenke as $idee): ?>
                            <?php $anlassNamen = Geschenkidee::anlassNamenInklGeburtstag((int) $idee['id']); ?>
                            <li>
                                <?php if (!empty($idee['text'])): ?>
                                    <?= htmlspecialchars($idee['text']) ?>
                                <?php endif; ?>
                                <?php if (!empty($anlassNamen)): ?>
                                    (<?= htmlspecialchars(implode(', ', $anlassNamen)) ?>)
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

            </section>

        <?php endforeach; ?>

        <a href="index.php">Zurück zur Startseite</a>

    </div>

</body>

</html>
