<?php

require_once __DIR__ . '/../ModelTestCase.php';
require_once __DIR__ . '/../../backend/models/Interesse.php';
require_once __DIR__ . '/../../backend/models/Person.php';

final class InteresseTest extends ModelTestCase
{
    public function testSetzenUndLesenInKategorienReihenfolge(): void
    {
        $personId = Person::erstellen('Anna', '1990-01-01', null, null);

        Interesse::fuerPersonSetzen($personId, ['reisen', 'kochen', 'lesen']);

        // Reihenfolge wie in KATEGORIEN, nicht wie uebergeben.
        $this->assertSame(['lesen', 'kochen', 'reisen'], Interesse::vonPerson($personId));
    }

    public function testSetzenErsetztKomplettUndVerwirftUnbekannteSchluessel(): void
    {
        $personId = Person::erstellen('Anna', '1990-01-01', null, null);
        Interesse::fuerPersonSetzen($personId, ['lesen', 'musik']);

        Interesse::fuerPersonSetzen($personId, ['musik', 'erfunden', "'; DROP TABLE personen; --", 'musik']);

        $this->assertSame(['musik'], Interesse::vonPerson($personId));
    }

    public function testLeereAuswahlEntferntAlleInteressen(): void
    {
        $personId = Person::erstellen('Anna', '1990-01-01', null, null);
        Interesse::fuerPersonSetzen($personId, ['lesen']);

        Interesse::fuerPersonSetzen($personId, []);

        $this->assertSame([], Interesse::vonPerson($personId));
    }

    public function testInteressenSindProPersonGetrennt(): void
    {
        $anna = Person::erstellen('Anna', '1990-01-01', null, null);
        $max = Person::erstellen('Max', '1990-01-01', null, null);

        Interesse::fuerPersonSetzen($anna, ['lesen']);
        Interesse::fuerPersonSetzen($max, ['gaming']);

        $this->assertSame(['lesen'], Interesse::vonPerson($anna));
        $this->assertSame(['gaming'], Interesse::vonPerson($max));
    }

    public function testLoeschenDerPersonEntferntIhreInteressen(): void
    {
        $personId = Person::erstellen('Anna', '1990-01-01', null, null);
        Interesse::fuerPersonSetzen($personId, ['lesen', 'musik']);

        Person::loeschen($personId);

        $anzahl = Datenbank::verbinden()
            ->query('SELECT COUNT(*) FROM person_interessen')
            ->fetchColumn();
        $this->assertSame(0, (int) $anzahl);
    }

    public function testBezeichnungenLiefertAnzeigenamen(): void
    {
        $this->assertSame(
            ['Kochen & Backen', 'Gaming'],
            Interesse::bezeichnungen(['gaming', 'kochen', 'unbekannt'])
        );
    }
}
