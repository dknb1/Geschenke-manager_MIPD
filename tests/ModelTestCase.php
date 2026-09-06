<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../backend/config/Datenbank.php';

/**
 * Gemeinsame Basis fuer die Model-Tests (AnlassTest/PersonTest/GeschenkideeTest): jeder Test
 * startet mit einer frischen In-Memory-Datenbank, und alle drei Klassen brauchten bisher
 * denselben "Suche in einer Liste von Arrays nach einer Spalte" Foreach-Code (z. B.
 * findePersonNachName()/findeAnlassNachName()) dupliziert. Die konkreten Testklassen behalten
 * ihre eigenen, sprechend benannten Wrapper-Methoden (bessere Lesbarkeit an den Aufrufstellen),
 * delegieren die eigentliche Suche aber hierher.
 */
abstract class ModelTestCase extends TestCase
{
    protected function setUp(): void
    {
        Datenbank::fuerTests();
    }

    protected function findeInListe(array $alle, string $spalte, string $wert): array
    {
        foreach ($alle as $eintrag) {
            if ($eintrag[$spalte] === $wert) {
                return $eintrag;
            }
        }

        $this->fail("Kein Eintrag mit $spalte = '$wert' gefunden.");
    }

    protected function gibtEsInListe(array $alle, string $spalte, string $wert): bool
    {
        foreach ($alle as $eintrag) {
            if ($eintrag[$spalte] === $wert) {
                return true;
            }
        }

        return false;
    }
}
