<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../backend/config/Datenbank.php';

/** Basis fuer Model-Tests: jeder Test bekommt eine frische Datenbank im Speicher. */
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
