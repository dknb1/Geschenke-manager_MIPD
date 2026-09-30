<?php
require_once __DIR__ . '/../backend/models/Person.php';
require_once __DIR__ . '/../backend/models/Geschenkidee.php';

// Oeffentliche, schlanke Seite ohne Login/Navbar - bewusst als Ausnahme von einer kuenftigen
// Login-Pflicht gedacht (siehe project_multiuser_decision): der Zugriffsschutz besteht
// ausschliesslich aus dem unratbaren Token (Person::shareTokenGenerieren()), nicht aus einer
// Session. Zeigt nur offene und festgelegte Geschenkideen - vergangene (bereits uebergebene)
// sind fuer die Kaufentscheidung nicht relevant und werden bewusst weggelassen.
$token = trim($_GET['token'] ?? '');
$person = Person::findenPerShareToken($token);

$offeneGeschenkideen = [];
$festeGeschenke = [];

if ($person !== null) {
    $geschenkideenSortiert = Geschenkidee::sortiereNachStatus(Geschenkidee::vonPerson((int) $person['id']));
    $offeneGeschenkideen = $geschenkideenSortiert['offen'];
    $festeGeschenke = $geschenkideenSortiert['fest'];
}
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">

    <title><?= $person !== null ? 'Geschenke und Ideen für ' . htmlspecialchars($person['name']) : 'Nicht verfügbar' ?></title>

    <link rel="stylesheet" href="css/style.css">
</head>

<body>

    <div class="page-container">

        <?php if ($person === null): ?>

            <h1>Nicht verfügbar</h1>
            <p>Dieser Link ist ungültig oder nicht mehr aktiv.</p>

        <?php else: ?>

            <h1>Geschenke und Ideen für <?= htmlspecialchars($person['name']) ?></h1>

            <h2>Geschenkideen</h2>

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
                            <p>Status: <strong><?= (int) ($idee['besorgt'] ?? 0) === 1 ? 'Bereits besorgt' : 'Noch offen' ?></strong></p>
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

            <h2>Festgelegte Geschenke</h2>

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
                            <p>Status: <strong><?= (int) ($idee['besorgt'] ?? 0) === 1 ? 'Bereits besorgt' : 'Noch offen' ?></strong></p>
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

        <?php endif; ?>

    </div>

</body>

</html>
