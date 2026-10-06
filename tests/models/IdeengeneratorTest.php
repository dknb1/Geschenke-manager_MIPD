<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../backend/models/Ideengenerator.php';

/** Testet nur die Teile ohne Netzwerk; der echte Groq-Aufruf wird von Hand getestet. */
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

    public function testPromptAufbauenUebernimmtInteressenAnlassUndBudget(): void
    {
        $prompt = Ideengenerator::promptAufbauen(['Kochbuch'], ['Reisen', 'Musik'], 'Weihnachten', 'bis_20');

        $this->assertStringContainsString('Interessen der Person: Reisen, Musik.', $prompt);
        $this->assertStringContainsString('Anlass: Weihnachten.', $prompt);
        $this->assertStringContainsString('Budget: bis 20 €.', $prompt);
        $this->assertStringContainsString('- Kochbuch', $prompt);
        // Im Prompt immer nur Zeilenumbrueche ohne \r, auch bei Windows-Zeilenenden in der Quelldatei.
        $this->assertStringNotContainsString("\r", $prompt);
    }

    public function testAntwortValidierenVereinheitlichtGeschuetzteBindestricheUndLeerzeichen(): void
    {
        $vorschlaege = Ideengenerator::antwortValidieren(
            "[\"Sushi\u{2011}Kochkurs\", \"Spotify\u{00A0}Karte\u{202F}10\u{00A0}€\", \"Buch – 100 Rezepte\"]"
        );

        $this->assertSame(['Sushi-Kochkurs', 'Spotify Karte 10 €', 'Buch – 100 Rezepte'], $vorschlaege);
    }

    public function testPromptAufbauenLaesstLeereUndUnbekannteAngabenWeg(): void
    {
        $prompt = Ideengenerator::promptAufbauen([], ['Lesen'], null, 'egal');

        $this->assertStringNotContainsString('Anlass:', $prompt);
        $this->assertStringNotContainsString('Budget:', $prompt);
        $this->assertStringNotContainsString('Bereits verschenkte', $prompt);

        // Manipuliertes Budget kommt nicht in den Prompt (den Anlass prueft die Seite selbst).
        $prompt = Ideengenerator::promptAufbauen(['Kochbuch'], [], null, '999 €');
        $this->assertStringNotContainsString('999', $prompt);
        $this->assertStringNotContainsString('Anlass:', Ideengenerator::promptAufbauen(['Kochbuch'], [], '   '));
    }

    public function testPromptAufbauenUebernimmtAnlassnameEinzeiligUndGekuerzt(): void
    {
        $prompt = Ideengenerator::promptAufbauen(['Kochbuch'], [], "Hochzeit
von   Anna
und Tom");
        $this->assertStringContainsString('Anlass: Hochzeit von Anna und Tom.', $prompt);

        $prompt = Ideengenerator::promptAufbauen(['Kochbuch'], [], str_repeat('a', 150));
        $this->assertStringContainsString('Anlass: ' . str_repeat('a', 100) . '.', $prompt);
        $this->assertStringNotContainsString(str_repeat('a', 101), $prompt);

        // Umlaute zaehlen als ein Zeichen und werden nicht mitten im Zeichen abgeschnitten.
        $prompt = Ideengenerator::promptAufbauen(['Kochbuch'], [], str_repeat('ä', 150));
        $this->assertStringContainsString('Anlass: ' . str_repeat('ä', 100) . '.', $prompt);
        $this->assertStringNotContainsString(str_repeat('ä', 101), $prompt);
    }

    public function testDatengrundlageIstDuennOhneInteressenUndMitWenigenIdeen(): void
    {
        $this->assertTrue(Ideengenerator::datengrundlageIstDuenn(0, 0));
        $this->assertTrue(Ideengenerator::datengrundlageIstDuenn(2, 0));
        $this->assertFalse(Ideengenerator::datengrundlageIstDuenn(3, 0));
        // Schon ein Interesse genuegt, auch ganz ohne Ideen.
        $this->assertFalse(Ideengenerator::datengrundlageIstDuenn(0, 1));
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

    public function testLetzteAnfrageUndLetzteAntwortSindLeerOhneGeneriereAufruf(): void
    {
        // Nur generiere() setzt Anfrage/Antwort fuer die Anzeige.
        $this->assertNull(Ideengenerator::letzteAnfrage());
        $this->assertNull(Ideengenerator::letzteAntwort());
    }
}
