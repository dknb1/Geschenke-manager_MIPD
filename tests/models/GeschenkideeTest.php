<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../backend/models/Person.php';
require_once __DIR__ . '/../../backend/models/Anlass.php';
require_once __DIR__ . '/../../backend/models/Geschenkidee.php';

final class GeschenkideeTest extends TestCase
{
    protected function setUp(): void
    {
        Datenbank::fuerTests();
    }

    private function testPersonAnlegen(string $name = 'Anna'): int
    {
        Person::erstellen($name, '2000-01-01', null, null);
        return (int) $this->findePersonNachName($name)['id'];
    }

    private function findePersonNachName(string $name): array
    {
        foreach (Person::alle() as $person) {
            if ($person['name'] === $name) {
                return $person;
            }
        }

        $this->fail("Person mit Namen '$name' wurde nicht gefunden.");
    }

    private function findeIdeeNachText(string $text): array
    {
        foreach (Geschenkidee::alle() as $idee) {
            if ($idee['text'] === $text) {
                return $idee;
            }
        }

        $this->fail("Geschenkidee mit Text '$text' wurde nicht gefunden.");
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

    public function testErstellenUndAlle(): void
    {
        $personId = $this->testPersonAnlegen('Max');

        Geschenkidee::erstellen($personId, 'Kopfhörer', 'https://example.com/kopfhoerer', null);

        $ideen = Geschenkidee::alle();

        $this->assertCount(1, $ideen);
        $this->assertSame('Kopfhörer', $ideen[0]['text']);
        $this->assertSame('https://example.com/kopfhoerer', $ideen[0]['link']);
        $this->assertSame('Max', $ideen[0]['person_name']);
    }

    public function testVonPersonLiefertNurIdeenDieserPerson(): void
    {
        $maxId = $this->testPersonAnlegen('Max');
        $annaId = $this->testPersonAnlegen('Anna');

        Geschenkidee::erstellen($maxId, 'Idee für Max', null, null);
        Geschenkidee::erstellen($annaId, 'Idee für Anna', null, null);

        $ideenVonMax = Geschenkidee::vonPerson($maxId);

        $this->assertCount(1, $ideenVonMax);
        $this->assertSame('Idee für Max', $ideenVonMax[0]['text']);
    }

    public function testBildLinkWirdOhneDateiGespeichert(): void
    {
        $personId = $this->testPersonAnlegen();

        Geschenkidee::erstellen($personId, null, null, 'https://example.com/bild.jpg');

        $ideen = Geschenkidee::alle();

        $this->assertNull($ideen[0]['text']);
        $this->assertSame('https://example.com/bild.jpg', $ideen[0]['bild_link']);
    }

    public function testHatInhaltErfordertMindestensEinFeld(): void
    {
        $this->assertFalse(Geschenkidee::hatInhalt('', '', ''));
        $this->assertTrue(Geschenkidee::hatInhalt('Text', '', ''));
        $this->assertTrue(Geschenkidee::hatInhalt('', 'https://example.com', ''));
        $this->assertTrue(Geschenkidee::hatInhalt('', '', 'https://example.com/bild.jpg'));
    }

    public function testIstGueltigerTextLehntSqlSchluesselwoerterAb(): void
    {
        $this->assertTrue(Geschenkidee::istGueltigerText('Ein schönes Buch'));
        $this->assertFalse(Geschenkidee::istGueltigerText("Idee'; DROP TABLE geschenkideen; --"));
        $this->assertFalse(Geschenkidee::istGueltigerText('UPDATE geschenkideen SET text = 1'));
        $this->assertFalse(Geschenkidee::istGueltigerText(str_repeat('a', 1001)));
    }

    public function testIstGueltigerTextAkzeptiertAlltagswoerter(): void
    {
        // "Union" ist ein zu gebraeuchliches Alltagswort und deshalb nicht mehr in der
        // Denylist (siehe Geschenkidee::SQL_SCHLUESSELWOERTER). "UPDATE" bleibt dagegen
        // bewusst denylisted, auch wenn "Update" ebenfalls ein Alltagswort ist - siehe
        // testIstGueltigerTextLehntSqlSchluesselwoerterAb().
        $this->assertTrue(Geschenkidee::istGueltigerText('Ein Buch über die Europäische Union'));
        // "Dropbox" enthaelt "DROP" nur als Teilzeichenkette, nicht als eigenstaendiges Wort -
        // die \b-Wortgrenzen duerfen das nicht faelschlich ablehnen.
        $this->assertTrue(Geschenkidee::istGueltigerText('Ein Dropbox-Abo'));
    }

    public function testIstGueltigeUrlPrueftFormatUndErlaubtLeer(): void
    {
        $this->assertTrue(Geschenkidee::istGueltigeUrl(''));
        $this->assertTrue(Geschenkidee::istGueltigeUrl('https://example.com/artikel'));
        $this->assertFalse(Geschenkidee::istGueltigeUrl('kein-link'));
    }

    public function testFindenGibtNullZurueckWennNichtVorhanden(): void
    {
        $this->assertNull(Geschenkidee::finden(999));
    }

    public function testAktualisierenAendertWerte(): void
    {
        $personId = $this->testPersonAnlegen('Max');
        Geschenkidee::erstellen($personId, 'Alte Idee', null, null);
        $id = (int) $this->findeIdeeNachText('Alte Idee')['id'];

        Geschenkidee::aktualisieren($id, $personId, 'Neue Idee', 'https://example.com', null);

        $aktualisiert = Geschenkidee::finden($id);
        $this->assertSame('Neue Idee', $aktualisiert['text']);
        $this->assertSame('https://example.com', $aktualisiert['link']);
    }

    public function testLoeschenEntferntEintrag(): void
    {
        $personId = $this->testPersonAnlegen();
        Geschenkidee::erstellen($personId, 'Zu löschen', null, null);
        $id = (int) $this->findeIdeeNachText('Zu löschen')['id'];

        $erfolg = Geschenkidee::loeschen($id);

        $this->assertTrue($erfolg);
        $this->assertNull(Geschenkidee::finden($id));
    }

    public function testLoeschenGibtFalseZurueckWennNichtVorhanden(): void
    {
        $this->assertFalse(Geschenkidee::loeschen(999));
    }

    public function testErstellenVerknuepftMehrereAnlaesse(): void
    {
        $personId = $this->testPersonAnlegen();
        Anlass::erstellen('Geburtstagsfeier', '2026-06-01', true, []);
        Anlass::erstellen('Jubiläum', '2026-07-01', true, []);
        $anlass1 = (int) $this->findeAnlassNachName('Geburtstagsfeier')['id'];
        $anlass2 = (int) $this->findeAnlassNachName('Jubiläum')['id'];

        Geschenkidee::erstellen($personId, 'Mehrfach-Idee', null, null, [$anlass1, $anlass2]);
        $id = (int) $this->findeIdeeNachText('Mehrfach-Idee')['id'];

        $namen = array_column(Geschenkidee::anlaesse($id), 'name');
        sort($namen);
        $this->assertSame(['Geburtstagsfeier', 'Jubiläum'], $namen);
    }

    public function testAktualisierenErsetztAnlassVerknuepfungKomplett(): void
    {
        $personId = $this->testPersonAnlegen();
        Anlass::erstellen('Anlass A', '2026-06-01', true, []);
        Anlass::erstellen('Anlass B', '2026-07-01', true, []);
        $anlassA = (int) $this->findeAnlassNachName('Anlass A')['id'];
        $anlassB = (int) $this->findeAnlassNachName('Anlass B')['id'];

        Geschenkidee::erstellen($personId, 'Idee mit Anlass A', null, null, [$anlassA]);
        $id = (int) $this->findeIdeeNachText('Idee mit Anlass A')['id'];

        Geschenkidee::aktualisieren($id, $personId, 'Idee mit Anlass A', null, null, [$anlassB]);

        $namen = array_column(Geschenkidee::anlaesse($id), 'name');
        $this->assertSame(['Anlass B'], $namen);
    }

    public function testLoeschenDesAnlassesEntferntNurDieVerknuepfungNichtDieIdee(): void
    {
        $personId = $this->testPersonAnlegen();
        Anlass::erstellen('Vergänglicher Anlass', '2026-06-01', true, []);
        $anlassId = (int) $this->findeAnlassNachName('Vergänglicher Anlass')['id'];

        Geschenkidee::erstellen($personId, 'Bleibt bestehen', null, null, [$anlassId]);
        $id = (int) $this->findeIdeeNachText('Bleibt bestehen')['id'];

        Anlass::loeschen($anlassId);

        $this->assertNotNull(Geschenkidee::finden($id));
        $this->assertCount(0, Geschenkidee::anlaesse($id));
    }

    public function testAnlassNamenInklGeburtstagOhneFlagEnthaeltKeinenGeburtstag(): void
    {
        // Eine Idee ist NICHT automatisch fuer den Geburtstag gedacht - z. B. ein reines
        // Hochzeitsgeschenk oder eine Idee ganz ohne Anlass darf den Geburtstag nicht zeigen.
        Person::erstellen('Max', '1990-06-15', null, null);
        $personId = (int) $this->findePersonNachName('Max')['id'];
        Anlass::erstellen('Hochzeit', '2026-06-01', true, []);
        $anlassId = (int) $this->findeAnlassNachName('Hochzeit')['id'];

        Geschenkidee::erstellen($personId, 'Hochzeitsgeschenk', null, null, [$anlassId]);
        $id = (int) $this->findeIdeeNachText('Hochzeitsgeschenk')['id'];

        $namen = Geschenkidee::anlassNamenInklGeburtstag($id);

        $this->assertSame(['Hochzeit'], $namen);
        $this->assertNotContains('Geburtstag Max', $namen);
    }

    public function testAnlassNamenInklGeburtstagMitGesetztemFlagEnthaeltGeburtstag(): void
    {
        Person::erstellen('Anna', '1985-03-20', null, null);
        $personId = (int) $this->findePersonNachName('Anna')['id'];
        Anlass::erstellen('Weihnachtsfeier', '2026-12-01', true, []);
        $anlassId = (int) $this->findeAnlassNachName('Weihnachtsfeier')['id'];

        Geschenkidee::erstellen($personId, 'Idee für Anna', null, null, [$anlassId], true);
        $id = (int) $this->findeIdeeNachText('Idee für Anna')['id'];

        $namen = Geschenkidee::anlassNamenInklGeburtstag($id);

        $this->assertContains('Weihnachtsfeier', $namen);
        $this->assertContains('Geburtstag Anna', $namen);
    }

    public function testAktualisierenAendertGeburtstagsFlag(): void
    {
        $personId = $this->testPersonAnlegen('Tim');
        Geschenkidee::erstellen($personId, 'Idee für Tim', null, null, [], false);
        $id = (int) $this->findeIdeeNachText('Idee für Tim')['id'];

        $this->assertNotContains('Geburtstag Tim', Geschenkidee::anlassNamenInklGeburtstag($id));

        Geschenkidee::aktualisieren($id, $personId, 'Idee für Tim', null, null, [], true);

        $this->assertContains('Geburtstag Tim', Geschenkidee::anlassNamenInklGeburtstag($id));
    }
}
