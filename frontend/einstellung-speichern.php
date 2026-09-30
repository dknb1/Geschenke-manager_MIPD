<?php
require_once __DIR__ . '/../backend/models/Einstellung.php';
require_once __DIR__ . '/../backend/models/Ruecksprung.php';

// Eigener Endpunkt fuer das Einstellungs-Formular der Navbar. Vorher sendete das Formular an
// die gerade geoeffnete Seite - auf Bearbeiten-Seiten (idee-bearbeiten.php,
// person-bearbeiten.php) wurde dieser POST dann als "Speichern" mit leeren Feldern
// interpretiert, weil deren Formularlogik vor der Navbar laeuft. Nach dem Speichern zurueck
// auf die Seite, von der aus die Einstellung geaendert wurde (inkl. ihrer URL-Parameter).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tage = filter_input(INPUT_POST, 'benachrichtigung_tage', FILTER_VALIDATE_INT);

    if ($tage !== false && $tage !== null && $tage >= 0 && $tage <= 365) {
        Einstellung::benachrichtigungTageSpeichern($tage);
    }
}

$zurueck = $_POST['zurueck'] ?? null;
header('Location: ' . (Ruecksprung::istGueltig($zurueck) ? $zurueck : 'index.php'));
exit;
