<?php

require_once __DIR__ . '/../ModelTestCase.php';
require_once __DIR__ . '/../../backend/models/Anlass.php';
require_once __DIR__ . '/../../backend/models/GeschenkideeFilter.php';
require_once __DIR__ . '/../../backend/models/Ruecksprung.php';

final class GeschenkideeFilterTest extends ModelTestCase
{
    private DateTimeImmutable $heute;
    private int $annaId;
    private int $maxId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->heute = new DateTimeImmutable('2026-10-08');

        $this->annaId = Person::erstellen('Anna', '1990-05-05', null, null);
        $this->maxId = Person::erstellen('Max Müller', '1985-03-03', null, null);

        Anlass::erstellen('Feier', '2026-12-01', false, [$this->annaId]);
        $feierId = (int) array_column(Anlass::alle(), 'id', 'name')['Feier'];

        Geschenkidee::erstellen($this->annaId, 'Äpfelkorb', null, null, [], false, true);
        Geschenkidee::erstellen($this->annaId, 'Buch', null, null);
        Geschenkidee::erstellen($this->maxId, 'Fussball', null, null, [], false, false, null, true);
        Geschenkidee::erstellen($this->maxId, 'Uhr', null, null);
        Geschenkidee::festMachen($this->ideeId('Uhr'), $feierId);
    }

    private function ideeId(string $text): int
    {
        return (int) array_column(Geschenkidee::alle(), 'id', 'text')[$text];
    }

    /** Texte je Rubrik, alphabetisch, damit die Reihenfolge der Erstellung keine Rolle spielt. */
    private function texte(array $anfrage): array
    {
        $ergebnis = [];
        foreach (GeschenkideeFilter::anwenden(GeschenkideeFilter::ausAnfrage($anfrage), $this->heute) as $status => $ideen) {
            $texte = array_column($ideen, 'text');
            sort($texte);
            $ergebnis[$status] = $texte;
        }

        return $ergebnis;
    }

    public function testStandardZeigtOffeneUndFesteOhneVergangene(): void
    {
        $this->assertSame(
            ['offen' => ['Buch', 'Äpfelkorb'], 'fest' => ['Uhr']],
            $this->texte([])
        );
        $this->assertFalse(GeschenkideeFilter::istAktiv(GeschenkideeFilter::ausAnfrage([])));
    }

    public function testStatusAuswahlUndKeinHaekchenBedeutetAlle(): void
    {
        $this->assertSame(['vergangen' => ['Fussball']], $this->texte(['filter' => '1', 'vergangen' => '1']));
        $this->assertSame(
            ['offen' => ['Buch', 'Äpfelkorb'], 'fest' => ['Uhr'], 'vergangen' => ['Fussball']],
            $this->texte(['filter' => '1'])
        );
    }

    public function testPersonBesorgtUndSuche(): void
    {
        $this->assertSame(['offen' => [], 'fest' => ['Uhr']], $this->texte(['person' => (string) $this->maxId]));
        $this->assertSame(['offen' => ['Äpfelkorb'], 'fest' => []], $this->texte(['besorgt' => '1']));
        $this->assertSame(['offen' => ['Buch'], 'fest' => ['Uhr']], $this->texte(['besorgt' => '0']));

        // Suche findet Text ohne Ruecksicht auf Gross-/Kleinschreibung und auch den Personennamen.
        $this->assertSame(['offen' => ['Äpfelkorb'], 'fest' => []], $this->texte(['suche' => 'äpfel']));
        $this->assertSame(['offen' => [], 'fest' => ['Uhr']], $this->texte(['suche' => 'müller']));
    }

    public function testUngueltigeWerteFallenAufDenStandardZurueck(): void
    {
        $filter = GeschenkideeFilter::ausAnfrage([
            'suche' => '<script>',
            'person' => '999',
            'besorgt' => 'vielleicht',
        ]);

        $this->assertSame(GeschenkideeFilter::ausAnfrage([]), $filter);
    }

    public function testAlsUrlIstEinGueltigesRuecksprungziel(): void
    {
        $this->assertSame('geschenkideen.php', GeschenkideeFilter::alsUrl(GeschenkideeFilter::ausAnfrage([])));

        $url = GeschenkideeFilter::alsUrl(GeschenkideeFilter::ausAnfrage([
            'filter' => '1',
            'suche' => 'Max Müller',
            'person' => (string) $this->maxId,
            'vergangen' => '1',
            'besorgt' => '0',
        ]));

        $this->assertTrue(Ruecksprung::istGueltig($url));

        // Aus der URL gelesen ergibt sich wieder derselbe Filter.
        parse_str(parse_url($url, PHP_URL_QUERY), $anfrage);
        $this->assertSame(
            ['suche' => 'Max Müller', 'person' => $this->maxId, 'status' => ['vergangen'], 'besorgt' => false],
            GeschenkideeFilter::ausAnfrage($anfrage)
        );
    }
}
