<?php
// Als erste Zeile jeder geschuetzten Seite einbinden. Ohne Login geht es zur Anmeldeseite.
require_once __DIR__ . '/../../backend/models/Anmeldung.php';

Anmeldung::sessionStarten();

if (!Anmeldung::istAngemeldet()) {
    header('Location: login.php');
    exit;
}
