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

    public function testPromptAufbauenUebernimmtInteressenAnlassUndBudget(): void
    {
        $prompt = Ideengenerator::promptAufbauen(['Kochbuch'], ['Reisen', 'Musik'], 'weihnachten', 'bis_20');

        $this->assertStringContainsString('Interessen der Person: Reisen, Musik.', $prompt);
        $this->assertStringContainsString('Anlass: Weihnachten.', $prompt);
        $this->assertStringContainsString('Budget: bis 20 €.', $prompt);
        $this->assertStringContainsString('- Kochbuch', $prompt);
        // Zeilenumbrueche im Prompt immer als \n, unabhaengig von den Zeilenenden der
        // Quelldatei (ein woertlicher Umbruch im String wuerde unter Windows zu \r\n).
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
        $prompt = Ideengenerator::promptAufbauen([], ['Lesen'], 'keiner', 'egal');

        $this->assertStringNotContainsString('Anlass:', $prompt);
        $this->assertStringNotContainsString('Budget:', $prompt);
        $this->assertStringNotContainsString('Bereits verschenkte', $prompt);

        // Manipulierte Formularwerte duerfen nicht als Freitext im Prompt landen.
        $prompt = Ideengenerator::promptAufbauen(['Kochbuch'], [], 'Hochzeit von Anna', '999 €');
        $this->assertStringNotContainsString('Anna', $prompt);
        $this->assertStringNotContainsString('999', $prompt);
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
        // promptAufbauen()/antwortValidieren() rufen nie anfrageSenden() auf und duerfen die
        // fuer die Transparenz-Anzeige (ideen-generieren.php) gedachten Werte deshalb nicht
        // setzen - nur generiere() selbst tut das (nicht automatisiert getestet, siehe oben).
        $this->assertNull(Ideengenerator::letzteAnfrage());
        $this->assertNull(Ideengenerator::letzteAntwort());
    }
}
