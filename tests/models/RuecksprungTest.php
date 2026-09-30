<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../backend/models/Ruecksprung.php';

final class RuecksprungTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_GET['zurueck']);
    }

    public function testErlaubtLokaleSeitenMitOptionalerId(): void
    {
        $this->assertTrue(Ruecksprung::istGueltig('person-anzeigen.php'));
        $this->assertTrue(Ruecksprung::istGueltig('person-bearbeiten.php?id=5'));
        $this->assertTrue(Ruecksprung::istGueltig('idee-speichern.php?person=12'));
        $this->assertTrue(Ruecksprung::istGueltig('seite.php?id=5&person=6'));
    }

    public function testErlaubtVerschachteltesGueltigesZurueck(): void
    {
        $this->assertTrue(Ruecksprung::istGueltig(
            'idee-bearbeiten.php?id=7&zurueck=person-bearbeiten.php%3Fid%3D3'
        ));
        $this->assertFalse(Ruecksprung::istGueltig(
            'idee-bearbeiten.php?id=7&zurueck=https%3A%2F%2Fboese.example%2F'
        ));
        $this->assertFalse(Ruecksprung::istGueltig(
            'idee-bearbeiten.php?id=7&zurueck=%2F%2Fboese.example%2Fx.php'
        ));
    }

    public function testLehntAlleFremdenOderManipuliertenZieleAb(): void
    {
        foreach ([
            'https://boese.example/',
            '//boese.example/x.php',
            '/etc/passwd.php',
            '../geheim.php',
            'javascript:alert(1)',
            "person-anzeigen.php\r\nSet-Cookie: x=1",
            "person-anzeigen.php\n",
            'person-bearbeiten.php?id=5&foo=bar',
            'person-bearbeiten.php?id=5&a[]=1',
            'person-bearbeiten.php?',
            'person-bearbeiten.php?id=%0d%0a',
            'person-bearbeiten.php?id=abc',
            'Person-Anzeigen.php',
            '',
            null,
            ['person-anzeigen.php'],
        ] as $ziel) {
            $this->assertFalse(Ruecksprung::istGueltig($ziel), var_export($ziel, true));
        }
    }

    public function testAusAnfrageFaelltAufStandardZurueck(): void
    {
        $this->assertSame('idee-speichern.php', Ruecksprung::ausAnfrage('idee-speichern.php'));

        $_GET['zurueck'] = 'https://boese.example/';
        $this->assertSame('idee-speichern.php', Ruecksprung::ausAnfrage('idee-speichern.php'));

        $_GET['zurueck'] = 'person-bearbeiten.php?id=3';
        $this->assertSame('person-bearbeiten.php?id=3', Ruecksprung::ausAnfrage('idee-speichern.php'));
    }

    public function testAnhaengenKodiertZielUndWaehltTrennzeichen(): void
    {
        $this->assertSame(
            'idee-bearbeiten.php?id=7&zurueck=person-bearbeiten.php%3Fid%3D3',
            Ruecksprung::anhaengen('idee-bearbeiten.php?id=7', 'person-bearbeiten.php?id=3')
        );
        $this->assertSame(
            'anlass-erstellen.php?zurueck=index.php',
            Ruecksprung::anhaengen('anlass-erstellen.php', 'index.php')
        );
    }

    public function testAktuelleSeiteEntferntVerzeichnisUndBehaeltQuery(): void
    {
        $this->assertSame(
            'idee-bearbeiten.php?id=7&zurueck=person-bearbeiten.php%3Fid%3D3',
            Ruecksprung::aktuelleSeite(
                '/geschenke/frontend/idee-bearbeiten.php?id=7&zurueck=person-bearbeiten.php%3Fid%3D3',
                'index.php'
            )
        );
        $this->assertSame('anlaesse.php', Ruecksprung::aktuelleSeite('/anlaesse.php', 'index.php'));
        $this->assertSame('index.php', Ruecksprung::aktuelleSeite('/', 'index.php'));
        $this->assertSame('index.php', Ruecksprung::aktuelleSeite('/seite.php?x=<script>', 'index.php'));
    }
}
