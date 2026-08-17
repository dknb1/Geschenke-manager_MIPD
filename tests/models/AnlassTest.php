<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../backend/models/Anlass.php';

final class AnlassTest extends TestCase
{
    protected function setUp(): void
    {
        Datenbank::fuerTests();
    }

    private function findeAnlassNachName(string $name): array
    {
        foreach (Anlass::alle() as $anlass) {
            if ($anlass['name'] === $name) {
                return $anlass;
            }
        }

        $this->fail("Anlass mit Namen '$name' wurde nicht gefunden.");
    }

    private function gibtEsAnlassMitName(string $name): bool
    {
        foreach (Anlass::alle() as $anlass) {
            if ($anlass['name'] === $name) {
                return true;
            }
        }

        return false;
    }

    public function testErstellenUndAlle(): void
    {
        Anlass::erstellen('Testgeburtstag', '2026-09-12', true, 'Max');

        $angelegt = $this->findeAnlassNachName('Testgeburtstag');

        $this->assertSame('2026-09-12', $angelegt['datum']);
        $this->assertSame(1, (int) $angelegt['wiederholt_jaehrlich']);
    }

    public function testFindenGibtNullZurueckWennNichtVorhanden(): void
    {
        $this->assertNull(Anlass::finden(999));
    }

    public function testAktualisierenAendertWerte(): void
    {
        Anlass::erstellen('Alter Name', '2026-01-01', false, null);
        $id = (int) $this->findeAnlassNachName('Alter Name')['id'];

        Anlass::aktualisieren($id, 'Neuer Name', '2026-02-02', true, 'Anna');

        $aktualisiert = Anlass::finden($id);
        $this->assertSame('Neuer Name', $aktualisiert['name']);
        $this->assertSame('Anna', $aktualisiert['person_name']);
    }

    public function testLoeschenEntferntEintrag(): void
    {
        Anlass::erstellen('Zu löschen', '2026-03-03', false, null);
        $id = (int) $this->findeAnlassNachName('Zu löschen')['id'];

        $erfolg = Anlass::loeschen($id);

        $this->assertTrue($erfolg);
        $this->assertFalse($this->gibtEsAnlassMitName('Zu löschen'));
    }

    public function testWeihnachtenIstStandardmaessigVorhandenUndGeschuetzt(): void
    {
        $weihnachten = $this->findeAnlassNachName('Weihnachten');

        $this->assertSame(1, (int) $weihnachten['geschuetzt']);
        $this->assertSame(1, (int) $weihnachten['wiederholt_jaehrlich']);
    }

    public function testGeschuetzterAnlassKannNichtGeloeschtWerden(): void
    {
        $weihnachten = $this->findeAnlassNachName('Weihnachten');

        $erfolg = Anlass::loeschen((int) $weihnachten['id']);

        $this->assertFalse($erfolg);
        $this->assertNotNull(Anlass::finden((int) $weihnachten['id']));
    }

    public function testNaechstesVorkommenBleibtDiesesJahrWennNochNichtVergangen(): void
    {
        $anlass = ['datum' => '2020-12-24', 'wiederholt_jaehrlich' => 1];
        $heute = new DateTimeImmutable('2026-06-01');

        $naechstes = Anlass::naechstesVorkommen($anlass, $heute);

        $this->assertSame('2026-12-24', $naechstes->format('Y-m-d'));
    }

    public function testNaechstesVorkommenSpringtInsNaechsteJahrWennBereitsVergangen(): void
    {
        $anlass = ['datum' => '2020-01-15', 'wiederholt_jaehrlich' => 1];
        $heute = new DateTimeImmutable('2026-06-01');

        $naechstes = Anlass::naechstesVorkommen($anlass, $heute);

        $this->assertSame('2027-01-15', $naechstes->format('Y-m-d'));
    }

    public function testNaechstesVorkommenBleibtUnveraendertWennNichtWiederkehrend(): void
    {
        $anlass = ['datum' => '2020-01-15', 'wiederholt_jaehrlich' => 0];
        $heute = new DateTimeImmutable('2026-06-01');

        $naechstes = Anlass::naechstesVorkommen($anlass, $heute);

        $this->assertSame('2020-01-15', $naechstes->format('Y-m-d'));
    }

    public function testAlleSortiertNachNaechstemVorkommenNichtNachGespeichertemDatum(): void
    {
        $heute = new DateTimeImmutable('today');
        $inZweiTagen = $heute->modify('+2 days');
        $vorEinemMonatWiederkehrend = $heute->modify('-1 month');

        Anlass::erstellen('Bald', $inZweiTagen->format('Y-m-d'), false, null);
        Anlass::erstellen('Wiederkehrend spaeter', $vorEinemMonatWiederkehrend->format('Y-m-d'), true, null);

        $namen = array_map(fn (array $a) => $a['name'], Anlass::alle());

        $this->assertLessThan(
            array_search('Wiederkehrend spaeter', $namen, true),
            array_search('Bald', $namen, true)
        );
    }
}
