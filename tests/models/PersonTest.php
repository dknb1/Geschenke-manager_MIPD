<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../backend/models/Person.php';
require_once __DIR__ . '/../../backend/models/Geschenkidee.php';
require_once __DIR__ . '/../../backend/models/Anlass.php';

final class PersonTest extends TestCase
{
    protected function setUp(): void
    {
        Datenbank::fuerTests();
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

    private function gibtEsPersonMitName(string $name): bool
    {
        foreach (Person::alle() as $person) {
            if ($person['name'] === $name) {
                return true;
            }
        }

        return false;
    }

    public function testErstellenUndAlle(): void
    {
        Person::erstellen('Max Mustermann', '1994-05-03', 'maennlich', 'Mag Fußball und Technik');

        $angelegt = $this->findePersonNachName('Max Mustermann');

        $this->assertSame('1994-05-03', $angelegt['geburtsdatum']);
        $this->assertSame('maennlich', $angelegt['geschlecht']);
        $this->assertSame('Mag Fußball und Technik', $angelegt['details']);
    }

    public function testErstellenOhneOptionaleFelder(): void
    {
        Person::erstellen('Anna', '2000-01-01', null, null);

        $angelegt = $this->findePersonNachName('Anna');

        $this->assertSame('2000-01-01', $angelegt['geburtsdatum']);
        $this->assertNull($angelegt['geschlecht']);
        $this->assertNull($angelegt['details']);
    }

    public function testFindenGibtNullZurueckWennNichtVorhanden(): void
    {
        $this->assertNull(Person::finden(999));
    }

    public function testAktualisierenAendertWerte(): void
    {
        Person::erstellen('Alter Name', '1990-01-01', 'weiblich', null);
        $id = (int) $this->findePersonNachName('Alter Name')['id'];

        Person::aktualisieren($id, 'Neuer Name', '1985-06-15', 'divers', 'Neue Details');

        $aktualisiert = Person::finden($id);
        $this->assertSame('Neuer Name', $aktualisiert['name']);
        $this->assertSame('1985-06-15', $aktualisiert['geburtsdatum']);
        $this->assertSame('divers', $aktualisiert['geschlecht']);
        $this->assertSame('Neue Details', $aktualisiert['details']);
    }

    public function testLoeschenEntferntEintrag(): void
    {
        Person::erstellen('Zu löschen', '2000-01-01', null, null);
        $id = (int) $this->findePersonNachName('Zu löschen')['id'];

        $erfolg = Person::loeschen($id);

        $this->assertTrue($erfolg);
        $this->assertFalse($this->gibtEsPersonMitName('Zu löschen'));
    }

    public function testLoeschenGibtFalseZurueckWennNichtVorhanden(): void
    {
        $this->assertFalse(Person::loeschen(999));
    }

    public function testLoeschenEntferntAuchZugehoerigeGeschenkideen(): void
    {
        Person::erstellen('Person mit Ideen', '2000-01-01', null, null);
        $id = (int) $this->findePersonNachName('Person mit Ideen')['id'];
        Geschenkidee::erstellen($id, 'Eine Idee', null, null);

        Person::loeschen($id);

        $this->assertCount(0, Geschenkidee::alle());
    }

    public function testAlterWirdAusGeburtsdatumBerechnet(): void
    {
        $person = ['geburtsdatum' => '1990-06-15'];

        $this->assertSame(34, Person::alter($person, new DateTimeImmutable('2025-01-01')));
        // Geburtstag in diesem Jahr noch nicht erreicht -> ein Jahr juenger als die reine
        // Jahresdifferenz nahelegen wuerde.
        $this->assertSame(33, Person::alter($person, new DateTimeImmutable('2024-03-01')));
    }

    public function testGeburtstagAlsAnlassLiefertAnlassFoermigesArray(): void
    {
        $person = ['id' => 5, 'name' => 'Max', 'geburtsdatum' => '1990-06-15'];

        $anlass = Person::geburtstagAlsAnlass($person);

        $this->assertSame('Geburtstag Max', $anlass['name']);
        $this->assertSame('1990-06-15', $anlass['datum']);
        $this->assertSame(1, $anlass['wiederholt_jaehrlich']);
        $this->assertSame(5, $anlass['person_id']);
        // Muss mit Anlass::naechstesVorkommen() kompatibel sein (gleiche erwartete Keys).
        $this->assertEquals(
            new DateTimeImmutable('2026-06-15'),
            Anlass::naechstesVorkommen($anlass, new DateTimeImmutable('2026-01-01'))
        );
    }

    public function testGueltigeNamenWerdenAkzeptiert(): void
    {
        $this->assertTrue(Person::istGueltigerName('Anna-Lena'));
        $this->assertTrue(Person::istGueltigerName("O'Brien"));
        $this->assertTrue(Person::istGueltigerName('Björk Müller'));
    }

    public function testUngueltigeNamenWerdenAbgelehnt(): void
    {
        $this->assertFalse(Person::istGueltigerName(''));
        $this->assertFalse(Person::istGueltigerName('Max123'));
        $this->assertFalse(Person::istGueltigerName("Robert'; DROP TABLE personen;--"));
        $this->assertFalse(Person::istGueltigerName(str_repeat('a', 101)));
    }

    public function testGueltigesGeburtsdatumWirdAkzeptiert(): void
    {
        $this->assertTrue(Person::istGueltigesGeburtsdatum('1994-05-03'));
        $this->assertTrue(Person::istGueltigesGeburtsdatum(date('Y-m-d')));
    }

    public function testUngueltigesGeburtsdatumWirdAbgelehnt(): void
    {
        $this->assertFalse(Person::istGueltigesGeburtsdatum(''));
        $this->assertFalse(Person::istGueltigesGeburtsdatum('kein-datum'));
        $this->assertFalse(Person::istGueltigesGeburtsdatum('2099-01-01'));
        $this->assertFalse(Person::istGueltigesGeburtsdatum('03.05.1994'));
    }

    public function testGueltigesGeschlechtWirdAkzeptiert(): void
    {
        $this->assertTrue(Person::istGueltigesGeschlecht(''));
        $this->assertTrue(Person::istGueltigesGeschlecht('maennlich'));
        $this->assertTrue(Person::istGueltigesGeschlecht('weiblich'));
        $this->assertTrue(Person::istGueltigesGeschlecht('divers'));
    }

    public function testUngueltigesGeschlechtWirdAbgelehnt(): void
    {
        $this->assertFalse(Person::istGueltigesGeschlecht('unbekannt'));
    }

    public function testDetailsMitSqlSchluesselwortWerdenAbgelehnt(): void
    {
        $this->assertFalse(Person::istGueltigeDetails("Test'; DROP TABLE personen;--"));
        $this->assertFalse(Person::istGueltigeDetails('SELECT * FROM personen'));
        $this->assertFalse(Person::istGueltigeDetails(str_repeat('a', 1001)));
    }

    public function testUnbedenklicheDetailsWerdenAkzeptiert(): void
    {
        $this->assertTrue(Person::istGueltigeDetails('Mag Bücher und Wandern.'));
        $this->assertTrue(Person::istGueltigeDetails(''));
    }
}
