<?php
require_once __DIR__ . '/../backend/models/Anmeldung.php';

// Nur per POST, damit ein eingebetteter Link niemanden unbemerkt abmeldet.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Anmeldung::sessionStarten();
    Anmeldung::abmelden();
}

header('Location: login.php');
exit;
