<?php

require_once __DIR__ . '/../ModelTestCase.php';
require_once __DIR__ . '/../../backend/models/Person.php';
require_once __DIR__ . '/../../backend/models/Geschenkidee.php';
require_once __DIR__ . '/../../backend/models/Anlass.php';

final class PersonTest extends ModelTestCase
{
    private function findePersonNachName(string $name): array
    {
        return $this->findeInListe(Person::alle(), 'name', $name);
    }

    private function gibtEsPersonMitName(string $name): bool
    {
        return $this->gibtEsInListe(Person::alle(), 'name', $name);
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
        $this->assertFalse(Person::istGueltigeDetails('UPDATE personen SET name = 1'));
        $this->assertFalse(Person::istGueltigeDetails(str_repeat('a', 1001)));
    }

    public function testUnbedenklicheDetailsWerdenAkzeptiert(): void
    {
        $this->assertTrue(Person::istGueltigeDetails('Mag Bücher und Wandern.'));
        $this->assertTrue(Person::istGueltigeDetails(''));
        // Alltagswoerter, die zufaellig wie SQL-Schluesselwoerter aussehen bzw. sie als
        // Teilzeichenkette enthalten, duerfen nicht faelschlich abgelehnt werden.
        $this->assertTrue(Person::istGueltigeDetails('Ihr Alter ist 30 Jahre'));
        $this->assertTrue(Person::istGueltigeDetails('Interessiert sich für die Europäische Union'));
        $this->assertTrue(Person::istGueltigeDetails('Nutzt Dropbox zum Teilen von Fotos'));
        $this->assertTrue(Person::istGueltigeDetails('Mag eine große Selection an Farben'));
    }

    public function testValidiereEingabeLiefertLeereListeBeiGueltigenWerten(): void
    {
        $fehler = Person::validiereEingabe('Anna-Lena', '1994-05-03', 'weiblich', 'Mag Bücher.');

        $this->assertSame([], $fehler);
    }

    public function testValidiereEingabeSammeltMehrereFehler(): void
    {
        $fehler = Person::validiereEingabe('', '', 'unbekannt', str_repeat('a', 1001));

        $this->assertCount(4, $fehler);
        $this->assertSame('Bitte einen Namen angeben.', $fehler[0]);
        $this->assertSame('Bitte ein Geburtsdatum angeben.', $fehler[1]);
    }

    public function testShareTokenGenerierenSetztTokenUndFindenPerShareTokenFindetPerson(): void
    {
        Person::erstellen('Anna', '2000-01-01', null, null);
        $id = (int) $this->findePersonNachName('Anna')['id'];
        $this->assertNull(Person::finden($id)['share_token']);

        $token = Person::shareTokenGenerieren($id);

        $this->assertNotNull($token);
        $this->assertSame(32, strlen($token));
        $this->assertSame($token, Person::finden($id)['share_token']);

        $gefunden = Person::findenPerShareToken($token);
        $this->assertSame($id, (int) $gefunden['id']);
    }

    public function testShareTokenGenerierenGibtNullZurueckWennPersonNichtExistiert(): void
    {
        $this->assertNull(Person::shareTokenGenerieren(999));
    }

    public function testShareTokenNeuGenerierenMachtAltenTokenUngueltig(): void
    {
        Person::erstellen('Anna', '2000-01-01', null, null);
        $id = (int) $this->findePersonNachName('Anna')['id'];

        $ersterToken = Person::shareTokenGenerieren($id);
        $zweiterToken = Person::shareTokenGenerieren($id);

        $this->assertNotSame($ersterToken, $zweiterToken);
        $this->assertNull(Person::findenPerShareToken($ersterToken));
        $this->assertSame($id, (int) Person::findenPerShareToken($zweiterToken)['id']);
    }

    public function testFindenPerShareTokenGibtNullZurueckBeiLeeremOderUnbekanntemToken(): void
    {
        $this->assertNull(Person::findenPerShareToken(''));
        $this->assertNull(Person::findenPerShareToken('unbekannter-token'));
    }

    public function testDarfIdeenGenerierenIstWahrOhneVorherigeGenerierung(): void
    {
        $person = ['ideen_generiert_am' => null];

        $this->assertTrue(Person::darfIdeenGenerieren($person));
    }

    public function testDarfIdeenGenerierenLehntInnerhalbDesCooldownsAb(): void
    {
        $person = ['ideen_generiert_am' => '2026-01-01 12:00:00'];
        $heute = new DateTimeImmutable('2026-01-01 12:00:30');

        $this->assertFalse(Person::darfIdeenGenerieren($person, $heute));
    }

    public function testDarfIdeenGenerierenErlaubtNachAblaufDesCooldowns(): void
    {
        $person = ['ideen_generiert_am' => '2026-01-01 12:00:00'];
        $heute = new DateTimeImmutable('2026-01-01 12:01:01');

        $this->assertTrue(Person::darfIdeenGenerieren($person, $heute));
    }

    public function testIdeenGenerierungVermerkenSetztZeitstempel(): void
    {
        Person::erstellen('Anna', '2000-01-01', null, null);
        $id = (int) $this->findePersonNachName('Anna')['id'];
        $this->assertNull(Person::finden($id)['ideen_generiert_am']);

        Person::ideenGenerierungVermerken($id);

        $this->assertNotNull(Person::finden($id)['ideen_generiert_am']);
    }
}
