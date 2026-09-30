<?php

require_once __DIR__ . '/../ModelTestCase.php';
require_once __DIR__ . '/../../backend/models/Anlass.php';
require_once __DIR__ . '/../../backend/models/Person.php';

final class AnlassTest extends ModelTestCase
{
    private function findeAnlassNachName(string $name): array
    {
        return $this->findeInListe(Anlass::alle(), 'name', $name);
    }

    private function gibtEsAnlassMitName(string $name): bool
    {
        return $this->gibtEsInListe(Anlass::alle(), 'name', $name);
    }

    public function testErstellenUndAlle(): void
    {
        Anlass::erstellen('Testgeburtstag', '2026-09-12', true, []);

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
        Anlass::erstellen('Alter Name', '2026-01-01', false, []);
        $id = (int) $this->findeAnlassNachName('Alter Name')['id'];

        Anlass::aktualisieren($id, 'Neuer Name', '2026-02-02', true, []);

        $aktualisiert = Anlass::finden($id);
        $this->assertSame('Neuer Name', $aktualisiert['name']);
        $this->assertSame('2026-02-02', $aktualisiert['datum']);
    }

    public function testLoeschenEntferntEintrag(): void
    {
        Anlass::erstellen('Zu löschen', '2026-03-03', false, []);
        $id = (int) $this->findeAnlassNachName('Zu löschen')['id'];

        $erfolg = Anlass::loeschen($id);

        $this->assertTrue($erfolg);
        $this->assertFalse($this->gibtEsAnlassMitName('Zu löschen'));
    }

    public function testErstellenVerknuepftMehrerePersonen(): void
    {
        Person::erstellen('Max', '1990-01-01', null, null);
        Person::erstellen('Anna', '1992-02-02', null, null);
        $maxId = (int) array_values(array_filter(Person::alle(), fn (array $p) => $p['name'] === 'Max'))[0]['id'];
        $annaId = (int) array_values(array_filter(Person::alle(), fn (array $p) => $p['name'] === 'Anna'))[0]['id'];

        Anlass::erstellen('Hochzeitstag', '2026-06-01', true, [$maxId, $annaId]);
        $id = (int) $this->findeAnlassNachName('Hochzeitstag')['id'];

        $namen = array_column(Anlass::personen($id), 'name');
        sort($namen);
        $this->assertSame(['Anna', 'Max'], $namen);
    }

    public function testAktualisierenErsetztPersonenverknuepfungKomplett(): void
    {
        Person::erstellen('Max', '1990-01-01', null, null);
        Person::erstellen('Anna', '1992-02-02', null, null);
        $maxId = (int) array_values(array_filter(Person::alle(), fn (array $p) => $p['name'] === 'Max'))[0]['id'];
        $annaId = (int) array_values(array_filter(Person::alle(), fn (array $p) => $p['name'] === 'Anna'))[0]['id'];

        Anlass::erstellen('Anlass mit Max', '2026-06-01', false, [$maxId]);
        $id = (int) $this->findeAnlassNachName('Anlass mit Max')['id'];

        Anlass::aktualisieren($id, 'Anlass mit Max', '2026-06-01', false, [$annaId]);

        $namen = array_column(Anlass::personen($id), 'name');
        $this->assertSame(['Anna'], $namen);
    }

    public function testLoeschenEntferntAuchPersonenverknuepfung(): void
    {
        Person::erstellen('Max', '1990-01-01', null, null);
        $maxId = (int) Person::alle()[0]['id'];

        Anlass::erstellen('Anlass mit Max', '2026-06-01', false, [$maxId]);
        $id = (int) $this->findeAnlassNachName('Anlass mit Max')['id'];

        Anlass::loeschen($id);

        $this->assertCount(0, Anlass::personen($id));
    }

    public function testVonPersonLiefertNurAnlaesseDieserPerson(): void
    {
        Person::erstellen('Max', '1990-01-01', null, null);
        Person::erstellen('Anna', '1992-02-02', null, null);
        $maxId = (int) array_values(array_filter(Person::alle(), fn (array $p) => $p['name'] === 'Max'))[0]['id'];
        $annaId = (int) array_values(array_filter(Person::alle(), fn (array $p) => $p['name'] === 'Anna'))[0]['id'];

        Anlass::erstellen('Anlass mit Max', '2026-06-01', false, [$maxId]);
        Anlass::erstellen('Anlass mit Anna', '2026-07-01', false, [$annaId]);

        $namenVonMax = array_column(Anlass::vonPerson($maxId), 'name');

        $this->assertSame(['Anlass mit Max'], $namenVonMax);
    }

    public function testGeschuetzterAnlassKannKeinerPersonZugeordnetWerden(): void
    {
        Person::erstellen('Max', '1990-01-01', null, null);
        $maxId = (int) Person::alle()[0]['id'];
        $weihnachten = $this->findeAnlassNachName('Weihnachten');

        Anlass::aktualisieren(
            (int) $weihnachten['id'],
            $weihnachten['name'],
            $weihnachten['datum'],
            true,
            [$maxId]
        );

        $this->assertCount(0, Anlass::personen((int) $weihnachten['id']));
    }

    public function testGeschuetzterAnlassBehaeltWiederholtJaehrlichBeiAktualisieren(): void
    {
        $weihnachten = $this->findeAnlassNachName('Weihnachten');
        $this->assertSame(1, (int) $weihnachten['wiederholt_jaehrlich']);

        // Versuch, die Wiederholung abzuschalten - muss ignoriert werden, sonst wuerde
        // naechstesVorkommen() das Datum kuenftig stumpf einfrieren statt hochzuzaehlen.
        Anlass::aktualisieren(
            (int) $weihnachten['id'],
            $weihnachten['name'],
            $weihnachten['datum'],
            false,
            []
        );

        $aktualisiert = Anlass::finden((int) $weihnachten['id']);
        $this->assertSame(1, (int) $aktualisiert['wiederholt_jaehrlich']);
    }

    public function testWeihnachtenIstStandardmaessigVorhandenUndGeschuetzt(): void
    {
        $weihnachten = $this->findeAnlassNachName('Weihnachten');

        $this->assertSame(1, (int) $weihnachten['geschuetzt']);
        $this->assertSame(1, (int) $weihnachten['wiederholt_jaehrlich']);
    }

    public function testGeschuetzteLiefertNurGeschuetzteAnlaesse(): void
    {
        Anlass::erstellen('Individueller Anlass', '2026-05-01', false, []);

        $namen = array_column(Anlass::geschuetzte(), 'name');

        $this->assertSame(['Weihnachten'], $namen);
    }

    public function testVonPersonInklGeschuetzteKombiniertBeideListen(): void
    {
        Person::erstellen('Max', '1990-01-01', null, null);
        Person::erstellen('Anna', '1992-02-02', null, null);
        $maxId = (int) array_values(array_filter(Person::alle(), fn (array $p) => $p['name'] === 'Max'))[0]['id'];
        $annaId = (int) array_values(array_filter(Person::alle(), fn (array $p) => $p['name'] === 'Anna'))[0]['id'];

        Anlass::erstellen('Anlass mit Max', '2026-06-01', false, [$maxId]);
        Anlass::erstellen('Anlass mit Anna', '2026-07-01', false, [$annaId]);

        $namenVonMax = array_column(Anlass::vonPersonInklGeschuetzte($maxId), 'name');

        // Weihnachten (geschuetzt, betrifft fachlich alle Personen) taucht zusaetzlich zum
        // individuell verknuepften Anlass auf, "Anlass mit Anna" dagegen nicht.
        $this->assertSame(['Anlass mit Max', 'Weihnachten'], $namenVonMax);
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

        Anlass::erstellen('Bald', $inZweiTagen->format('Y-m-d'), false, []);
        Anlass::erstellen('Wiederkehrend spaeter', $vorEinemMonatWiederkehrend->format('Y-m-d'), true, []);

        $namen = array_map(fn (array $a) => $a['name'], Anlass::alle());

        $this->assertLessThan(
            array_search('Wiederkehrend spaeter', $namen, true),
            array_search('Bald', $namen, true)
        );
    }

    public function testValidiereNameUndDatumLiefertLeereListeBeiGueltigenWerten(): void
    {
        $this->assertSame([], Anlass::validiereNameUndDatum('Hochzeit', '2026-06-01'));
    }

    public function testValidiereNameUndDatumLehntLeerenNamenUndUngueltigesDatumAb(): void
    {
        $fehler = Anlass::validiereNameUndDatum('', 'kein-datum');

        $this->assertCount(2, $fehler);
        $this->assertSame('Bitte einen Namen für den Anlass angeben.', $fehler[0]);
        $this->assertSame('Bitte ein gültiges Datum angeben.', $fehler[1]);
    }

    public function testAlleInklGeburtstageEnthaeltEchteAnlaesseUndGeburtstage(): void
    {
        Person::erstellen('Max', '1990-06-15', null, null);
        Anlass::erstellen('Hochzeit', '2026-06-01', false, []);

        $namen = array_column(Anlass::alleInklGeburtstage(), 'name');

        $this->assertContains('Weihnachten', $namen);
        $this->assertContains('Hochzeit', $namen);
        $this->assertContains('Geburtstag Max', $namen);
    }

    public function testAlleInklGeburtstageKennzeichnetGeburtstageKorrekt(): void
    {
        Person::erstellen('Max', '1990-06-15', null, null);

        $alle = Anlass::alleInklGeburtstage();
        $geburtstag = $this->findeInListe($alle, 'name', 'Geburtstag Max');
        $weihnachten = $this->findeInListe($alle, 'name', 'Weihnachten');

        $this->assertTrue($geburtstag['ist_geburtstag']);
        $this->assertFalse($weihnachten['ist_geburtstag']);
    }

    public function testPersonenMitGeburtstagImNaechstenMonatFiltertNachMonat(): void
    {
        $heute = new DateTimeImmutable('2026-09-12');

        // Geburtstag faellt (dieses Jahr betrachtet) in den naechsten Kalendermonat (Oktober).
        Person::erstellen('Im Oktober', '1990-10-05', null, null);
        // Geburtstag liegt noch im aktuellen Monat (September) - zaehlt NICHT als "naechster Monat".
        Person::erstellen('Noch im September', '1990-09-20', null, null);
        // Geburtstag liegt erst in zwei Monaten (November) - ebenfalls nicht "naechster Monat".
        Person::erstellen('Im November', '1990-11-01', null, null);

        $namen = array_column(Anlass::personenMitGeburtstagImNaechstenMonat($heute), 'name');

        $this->assertSame(['Im Oktober'], $namen);
    }

    public function testPersonenMitGeburtstagImNaechstenMonatBeruecksichtigtJahreswechsel(): void
    {
        // Heute im Dezember: "naechster Monat" ist Januar des Folgejahres.
        $heute = new DateTimeImmutable('2026-12-15');

        Person::erstellen('Im Januar', '1990-01-10', null, null);
        Person::erstellen('Im Dezember', '1990-12-24', null, null);

        $namen = array_column(Anlass::personenMitGeburtstagImNaechstenMonat($heute), 'name');

        $this->assertSame(['Im Januar'], $namen);
    }

    public function testPersonenMitAnstehendemGeburtstagVereinigtFensterUndNaechstenMonat(): void
    {
        $heute = new DateTimeImmutable('2026-09-12');

        // Heute - muss auftauchen (fiel frueher komplett aus der Glocke, siehe Anlass.php).
        Person::erstellen('Heute', '1990-09-12', null, null);
        // Noch im September, innerhalb von 7 Tagen - nur ueber das Erinnerungsfenster gefunden.
        Person::erstellen('In fuenf Tagen', '1990-09-17', null, null);
        // Noch im September, aber ausserhalb des Fensters - weder Fenster noch naechster Monat.
        Person::erstellen('Ende September', '1990-09-28', null, null);
        // Naechster Kalendermonat - unabhaengig vom Fenster enthalten.
        Person::erstellen('Im Oktober', '1990-10-25', null, null);
        // Uebernaechster Monat - nicht enthalten.
        Person::erstellen('Im November', '1990-11-01', null, null);

        $namen = array_column(Anlass::personenMitAnstehendemGeburtstag(7, $heute), 'name');

        $this->assertSame(['Heute', 'In fuenf Tagen', 'Im Oktober'], $namen);
    }

    public function testPersonenMitAnstehendemGeburtstagGrossesFensterReichtUeberNaechstenMonatHinaus(): void
    {
        $heute = new DateTimeImmutable('2026-09-12');

        Person::erstellen('Im November', '1990-11-01', null, null);
        Person::erstellen('Im Januar', '1990-01-10', null, null);

        $namen = array_column(Anlass::personenMitAnstehendemGeburtstag(60, $heute), 'name');

        $this->assertSame(['Im November'], $namen);
    }
}
