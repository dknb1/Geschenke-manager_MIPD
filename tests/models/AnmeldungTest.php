<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../backend/models/Anmeldung.php';

final class AnmeldungTest extends TestCase
{
    public function testRichtigesPasswortPasstZumHash(): void
    {
        $hash = password_hash('Richtig#123', PASSWORD_DEFAULT);

        $this->assertTrue(Anmeldung::passwortKorrekt('Richtig#123', $hash));
        $this->assertFalse(Anmeldung::passwortKorrekt('richtig#123', $hash));
    }

    public function testOhneHashIstKeinLoginMoeglich(): void
    {
        $this->assertFalse(Anmeldung::passwortKorrekt('egal', null));
        $this->assertFalse(Anmeldung::passwortKorrekt('egal', ''));
    }

    public function testLeeresPasswortWirdAbgelehnt(): void
    {
        $this->assertFalse(Anmeldung::passwortKorrekt('', password_hash('', PASSWORD_DEFAULT)));
    }
}
