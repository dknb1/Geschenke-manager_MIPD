<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../backend/models/Person.php';
require_once __DIR__ . '/../../backend/models/Geschenkidee.php';

final class GeschenkideeTest extends TestCase
{
    protected function setUp(): void
    {
        Datenbank::fuerTests();
    }

    private function testPersonAnlegen(string $name = 'Anna'): int
    {
        Person::erstellen($name, null, null, null);
        return (int) Person::alle()[0]['id'];
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
        Person::erstellen('Anna', null, null, null);
        $annaId = (int) Person::alle()[1]['id'];

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
        $this->assertFalse(Geschenkidee::istGueltigerText(str_repeat('a', 1001)));
    }

    public function testIstGueltigeUrlPrueftFormatUndErlaubtLeer(): void
    {
        $this->assertTrue(Geschenkidee::istGueltigeUrl(''));
        $this->assertTrue(Geschenkidee::istGueltigeUrl('https://example.com/artikel'));
        $this->assertFalse(Geschenkidee::istGueltigeUrl('kein-link'));
    }
}
