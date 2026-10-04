<?php

require_once __DIR__ . '/../ModelTestCase.php';
require_once __DIR__ . '/../../backend/models/AnlassFilter.php';
require_once __DIR__ . '/../../backend/models/Ruecksprung.php';

final class AnlassFilterTest extends ModelTestCase
{
    private DateTimeImmutable $heute;

    protected function setUp(): void
    {
        parent::setUp();
        $this->heute = new DateTimeImmutable('2026-10-04');
    }

    private function namen(array $anfrage): array
    {
        return array_column(AnlassFilter::anwenden(AnlassFilter::ausAnfrage($anfrage), $this->heute), 'name');
    }

    public function testStandardBlendetNurVergangeneEinmaligeAnlaesseAus(): void
    {
        Anlass::erstellen('Schon vorbei', '2026-09-01', false);
        Anlass::erstellen('Jaehrlich im September', '2020-09-01', true);
        Person::erstellen('Max', '1990-10-10', null, null);

        $this->assertSame(
            ['Geburtstag Max', 'Weihnachten', 'Jaehrlich im September'],
            $this->namen([])
        );
        $this->assertContains('Schon vorbei', $this->namen(['vergangene' => '1']));
    }

    public function testZeitraumBegrenztAufTageAbHeute(): void
    {
        Anlass::erstellen('In 7 Tagen', '2026-10-11', false);
        Anlass::erstellen('In 8 Tagen', '2026-10-12', false);
        Anlass::erstellen('Vorbei', '2026-10-01', false);

        // Vergangene bleiben auch mit Haekchen draussen, sobald ein Zeitraum gewaehlt ist.
        $this->assertSame(['In 7 Tagen'], $this->namen(['zeitraum' => '7', 'vergangene' => '1']));
    }

    public function testArtenUnterscheidenGeburtstagEigeneUndPflicht(): void
    {
        Anlass::erstellen('Hochzeit', '2026-11-01', false);
        Person::erstellen('Max', '1990-10-10', null, null);

        $this->assertSame(['Geburtstag Max'], $this->namen(['filter' => '1', 'geburtstage' => '1']));
        $this->assertSame(['Hochzeit'], $this->namen(['filter' => '1', 'eigene' => '1']));
        $this->assertSame(['Weihnachten'], $this->namen(['filter' => '1', 'pflicht' => '1']));
        // Alles abgewaehlt wird wie "alles" behandelt statt einer leeren Liste.
        $this->assertCount(3, $this->namen(['filter' => '1']));
    }

    public function testPersonZeigtGeburtstagVerknuepfteUndPflichtanlaesse(): void
    {
        $max = Person::erstellen('Max', '1990-10-10', null, null);
        $anna = Person::erstellen('Anna', '1990-11-11', null, null);
        Anlass::erstellen('Hochzeit Max', '2026-11-01', false, [$max]);
        Anlass::erstellen('Umzug Anna', '2026-11-02', false, [$anna]);

        $this->assertSame(
            ['Geburtstag Max', 'Hochzeit Max', 'Weihnachten'],
            $this->namen(['person' => (string) $max])
        );
    }

    public function testSucheInAnlassUndPersonOhneGrossKleinschreibung(): void
    {
        $anna = Person::erstellen('Anna Krüger', '1990-11-11', null, null);
        Anlass::erstellen('Umzug', '2026-11-02', false, [$anna]);
        Anlass::erstellen('Hochzeit', '2026-11-03', false);

        // Trifft den Geburtstag (Name) und den Umzug (verknuepfte Person), Umlaut in Grossschrift.
        $this->assertSame(['Umzug', 'Geburtstag Anna Krüger'], $this->namen(['suche' => 'KRÜGER']));
        $this->assertSame(['Hochzeit'], $this->namen(['suche' => '  hoch ']));
    }

    public function testManipulierteWerteFallenAufStandardZurueck(): void
    {
        $filter = AnlassFilter::ausAnfrage([
            'suche' => '<script>',
            'person' => '999',
            'zeitraum' => '12345',
            'filter' => '1',
            'geburtstage' => ['1'],
            'vergangene' => 'ja',
        ]);

        $this->assertSame(AnlassFilter::ausAnfrage([]), $filter);
        $this->assertFalse(AnlassFilter::istAktiv($filter));
    }

    public function testAlsUrlIstGueltigesRuecksprungzielUndErgibtDenselbenFilter(): void
    {
        $max = Person::erstellen('Max', '1990-10-10', null, null);
        $anfrage = [
            'filter' => '1', 'suche' => "Anna-Lena O'Brien", 'person' => (string) $max,
            'zeitraum' => '30', 'eigene' => '1', 'vergangene' => '1',
        ];
        $filter = AnlassFilter::ausAnfrage($anfrage);
        $url = AnlassFilter::alsUrl($filter);

        $this->assertTrue(Ruecksprung::istGueltig($url), $url);
        // Auch verschachtelt, wie in den Links der Liste (anlass-bearbeiten.php?id=..&zurueck=..).
        $this->assertTrue(Ruecksprung::istGueltig(Ruecksprung::anhaengen('anlass-bearbeiten.php?id=3', $url)));

        parse_str(parse_url($url, PHP_URL_QUERY), $zurueckGelesen);
        $this->assertSame($filter, AnlassFilter::ausAnfrage($zurueckGelesen));
        $this->assertSame('anlaesse.php', AnlassFilter::alsUrl(AnlassFilter::ausAnfrage([])));
    }

    public function testNachMonatGruppiertMitDeutschenMonatsnamen(): void
    {
        Anlass::erstellen('Hochzeit', '2026-11-01', false);
        Anlass::erstellen('Neujahr', '2027-01-01', false);

        $gruppen = AnlassFilter::nachMonatGruppiert(
            AnlassFilter::anwenden(AnlassFilter::ausAnfrage([]), $this->heute)
        );

        $this->assertSame(['November 2026', 'Dezember 2026', 'Januar 2027'], array_keys($gruppen));
    }
}
