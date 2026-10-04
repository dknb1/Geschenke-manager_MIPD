<?php
require_once __DIR__ . '/../backend/models/Einstellung.php';
require_once __DIR__ . '/../backend/models/Ruecksprung.php';

// Speichert die Glocken-Einstellung. Eigene Seite, damit Bearbeiten-Seiten den POST nicht als
// ihr eigenes Speichern verstehen. Danach zurueck zur vorherigen Seite.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tage = filter_input(INPUT_POST, 'benachrichtigung_tage', FILTER_VALIDATE_INT);

    if ($tage !== false && $tage !== null && $tage >= 0 && $tage <= 365) {
        Einstellung::benachrichtigungTageSpeichern($tage);
    }
}

$zurueck = $_POST['zurueck'] ?? null;
header('Location: ' . (Ruecksprung::istGueltig($zurueck) ? $zurueck : 'index.php'));
exit;
