<?php
require_once __DIR__ . '/../backend/models/Person.php';
require_once __DIR__ . '/../backend/models/Anlass.php';
require_once __DIR__ . '/../backend/models/Geschenkidee.php';

// Gesamtliste: alle Personen mit allen zu ihnen gehoerenden Informationen (Anlaesse,
// aktuelle Geschenkideen, vergangene Geschenke) auf einer einzigen, druckbaren HTML-Seite -
// im Unterschied zu person-bearbeiten.php, das dieselben Informationen jeweils nur fuer
// EINE Person zeigt (und zusaetzlich das Bearbeitungsformular enthaelt).
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

    <?php include 'includes/navbar.php'; ?>

    <div class="page-container">

        <h1>Gesamtliste</h1>

        <button type="button" class="gesamtliste-drucken" onclick="window.print()">Drucken</button>

        <?php if (empty($personen)): ?>

            <p>Es sind noch keine Personen angelegt.</p>

        <?php endif; ?>

        <?php foreach ($personen as $person): ?>

            <?php
            $verknuepfteAnlaesse = Anlass::vonPersonInklGeschuetzte((int) $person['id']);

            $geschenkideenSortiert = Geschenkidee::sortiereAktuellUndVergangen(
                Geschenkidee::vonPerson((int) $person['id'])
            );
            $aktuelleGeschenkideen = $geschenkideenSortiert['aktuell'];
            $vergangeneGeschenke = $geschenkideenSortiert['vergangen'];

            $geburtstagAlsAnlass = Person::geburtstagAlsAnlass($person);
            ?>

            <section class="gesamtliste-person">

                <h2><?= htmlspecialchars($person['name']) ?></h2>

                <p>
                    Geburtstag:
                    <?= htmlspecialchars(Anlass::naechstesVorkommen($geburtstagAlsAnlass)->format('d.m.Y')) ?>
                    (<?= Person::alter($person) ?> Jahre)
                    <?php if (!empty($person['details'])): ?>
                        &ndash; <?= htmlspecialchars($person['details']) ?>
                    <?php endif; ?>
                </p>

                <h3>Anlässe</h3>

                <p>
                    <?= htmlspecialchars($geburtstagAlsAnlass['name']) ?> -
                    <?= htmlspecialchars(Anlass::naechstesVorkommen($geburtstagAlsAnlass)->format('d.m.Y')) ?>
                    <strong>(Geburtstag)</strong>
                </p>

                <?php if (empty($verknuepfteAnlaesse)): ?>
                    <p>Keine weiteren Anlässe hinterlegt.</p>
                <?php else: ?>
                    <ul class="ideen-liste">
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
                <?php endif; ?>

                <h3>Aktuelle Geschenkideen</h3>

                <?php if (empty($aktuelleGeschenkideen)): ?>
                    <p>Keine aktuellen Geschenkideen hinterlegt.</p>
                <?php else: ?>
                    <ul class="ideen-liste">
                        <?php foreach ($aktuelleGeschenkideen as $idee): ?>
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

                                <?php if (!empty($idee['geschenk_datum'])): ?>
                                    <p>
                                        Geschenkt am:
                                        <?= htmlspecialchars((new DateTimeImmutable($idee['geschenk_datum']))->format('d.m.Y')) ?>
                                    </p>
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
