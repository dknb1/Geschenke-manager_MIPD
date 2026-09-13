<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../backend/models/Ideengenerator.php';

/**
 * Testet nur die reinen, netzwerkfreien Teile (promptAufbauen()/antwortValidieren()) - der
 * eigentliche Groq-API-Aufruf (anfrageSenden()) braucht einen echten API-Key und Netzwerk und
 * wird bewusst nicht automatisiert getestet (manuell verifiziert, siehe Testabschlussbericht).
 */
final class IdeengeneratorTest extends TestCase
{
    public function testPromptAufbauenEnthaeltAlleBeispieleUndAnforderungAnDasFormat(): void
    {
        $prompt = Ideengenerator::promptAufbauen(['Kochbuch', 'Yogamatte']);

        $this->assertStringContainsString('- Kochbuch', $prompt);
        $this->assertStringContainsString('- Yogamatte', $prompt);
        $this->assertStringContainsString('JSON-Array', $prompt);
        $this->assertStringContainsString('drei', $prompt);
    }

    public function testAntwortValidierenAkzeptiertGueltigesJsonArrayMitDreiStrings(): void
    {
        $vorschlaege = Ideengenerator::antwortValidieren('["Kopfhörer", "Kochbuch", "Wanderrucksack"]');

        $this->assertSame(['Kopfhörer', 'Kochbuch', 'Wanderrucksack'], $vorschlaege);
    }

    public function testAntwortValidierenEntferntMarkdownCodebloecke(): void
    {
        $vorschlaege = Ideengenerator::antwortValidieren("```json\n[\"A\", \"B\", \"C\"]\n```");

        $this->assertSame(['A', 'B', 'C'], $vorschlaege);
    }

    public function testAntwortValidierenKuerztAufDreiBeiMehrVorschlaegen(): void
    {
        $vorschlaege = Ideengenerator::antwortValidieren('["A", "B", "C", "D"]');

        $this->assertSame(['A', 'B', 'C'], $vorschlaege);
    }

    public function testAntwortValidierenLehntWenigerAlsDreiVorschlaegeAb(): void
    {
        $this->assertNull(Ideengenerator::antwortValidieren('["A", "B"]'));
    }

    public function testAntwortValidierenLehntUngueltigesJsonAb(): void
    {
        $this->assertNull(Ideengenerator::antwortValidieren('kein json'));
    }

    public function testAntwortValidierenLehntLeereOderNichtStringWerteAb(): void
    {
        $this->assertNull(Ideengenerator::antwortValidieren('["A", "", "C"]'));
        $this->assertNull(Ideengenerator::antwortValidieren('["A", 2, "C"]'));
    }
}
