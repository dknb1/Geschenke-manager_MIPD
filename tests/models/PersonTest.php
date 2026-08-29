<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../backend/models/Person.php';

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
        Person::erstellen('Max Mustermann', 32, 'maennlich', 'Mag Fußball und Technik');

        $angelegt = $this->findePersonNachName('Max Mustermann');

        $this->assertSame(32, (int) $angelegt['alter']);
        $this->assertSame('maennlich', $angelegt['geschlecht']);
        $this->assertSame('Mag Fußball und Technik', $angelegt['details']);
    }

    public function testErstellenOhneOptionaleFelder(): void
    {
        Person::erstellen('Anna', null, null, null);

        $angelegt = $this->findePersonNachName('Anna');

        $this->assertNull($angelegt['alter']);
        $this->assertNull($angelegt['geschlecht']);
        $this->assertNull($angelegt['details']);
    }

    public function testFindenGibtNullZurueckWennNichtVorhanden(): void
    {
        $this->assertNull(Person::finden(999));
    }

    public function testAktualisierenAendertWerte(): void
    {
        Person::erstellen('Alter Name', 20, 'weiblich', null);
        $id = (int) $this->findePersonNachName('Alter Name')['id'];

        Person::aktualisieren($id, 'Neuer Name', 25, 'divers', 'Neue Details');

        $aktualisiert = Person::finden($id);
        $this->assertSame('Neuer Name', $aktualisiert['name']);
        $this->assertSame(25, (int) $aktualisiert['alter']);
        $this->assertSame('divers', $aktualisiert['geschlecht']);
        $this->assertSame('Neue Details', $aktualisiert['details']);
    }

    public function testLoeschenEntferntEintrag(): void
    {
        Person::erstellen('Zu löschen', null, null, null);
        $id = (int) $this->findePersonNachName('Zu löschen')['id'];

        $erfolg = Person::loeschen($id);

        $this->assertTrue($erfolg);
        $this->assertFalse($this->gibtEsPersonMitName('Zu löschen'));
    }

    public function testLoeschenGibtFalseZurueckWennNichtVorhanden(): void
    {
        $this->assertFalse(Person::loeschen(999));
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

    public function testGueltigesAlterWirdAkzeptiert(): void
    {
        $this->assertTrue(Person::istGueltigesAlter(''));
        $this->assertTrue(Person::istGueltigesAlter('0'));
        $this->assertTrue(Person::istGueltigesAlter('120'));
    }

    public function testUngueltigesAlterWirdAbgelehnt(): void
    {
        $this->assertFalse(Person::istGueltigesAlter('-1'));
        $this->assertFalse(Person::istGueltigesAlter('121'));
        $this->assertFalse(Person::istGueltigesAlter('abc'));
        $this->assertFalse(Person::istGueltigesAlter('1 OR 1=1'));
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
