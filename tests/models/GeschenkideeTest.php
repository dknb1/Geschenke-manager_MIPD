<?php

require_once __DIR__ . '/../ModelTestCase.php';
require_once __DIR__ . '/../../backend/models/Person.php';
require_once __DIR__ . '/../../backend/models/Anlass.php';
require_once __DIR__ . '/../../backend/models/Geschenkidee.php';

final class GeschenkideeTest extends ModelTestCase
{
    private function testPersonAnlegen(string $name = 'Anna'): int
    {
        Person::erstellen($name, '2000-01-01', null, null);
        return (int) $this->findePersonNachName($name)['id'];
    }

    private function findePersonNachName(string $name): array
    {
        return $this->findeInListe(Person::alle(), 'name', $name);
    }

    private function findeIdeeNachText(string $text): array
    {
        return $this->findeInListe(Geschenkidee::alle(), 'text', $text);
    }

    private function findeAnlassNachName(string $name): array
    {
        return $this->findeInListe(Anlass::alle(), 'name', $name);
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
        // "Union" ist erlaubt (Alltagswort), UPDATE bleibt gesperrt.
        $this->assertTrue(Geschenkidee::istGueltigerText('Ein Buch über die Europäische Union'));
        // "Dropbox" enthaelt DROP nur als Wortteil.
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
        // Ohne Haekchen ist eine Idee nicht automatisch fuer den Geburtstag.
        Person::erstellen('Max', '1990-06-15', null, null);
        $personId = (int) $this->findePersonNachName('Max')['id'];
        Anlass::erstellen('Hochzeit', '2026-06-01', true, []);
        $anlassId = (int) $this->findeAnlassNachName('Hochzeit')['id'];

        Geschenkidee::erstellen($personId, 'Hochzeitsgeschenk', null, null, [$anlassId]);
        $id = (int) $this->findeIdeeNachText('Hochzeitsgeschenk')['id'];

        $namen = Geschenkidee::anlassNamenInklGeburtstag($id, new DateTimeImmutable('2026-01-01'));

        $this->assertSame(['Hochzeit - 01.06.2026'], $namen);
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

        $namen = Geschenkidee::anlassNamenInklGeburtstag($id, new DateTimeImmutable('2026-01-01'));

        $this->assertContains('Weihnachtsfeier - 01.12.2026', $namen);
        $this->assertContains('Geburtstag Anna - 20.03.2026', $namen);
    }

    public function testAktualisierenAendertGeburtstagsFlag(): void
    {
        $heute = new DateTimeImmutable('2026-06-01');
        $personId = $this->testPersonAnlegen('Tim');
        Geschenkidee::erstellen($personId, 'Idee für Tim', null, null, [], false);
        $id = (int) $this->findeIdeeNachText('Idee für Tim')['id'];

        $this->assertNotContains('Geburtstag Tim', Geschenkidee::anlassNamenInklGeburtstag($id, $heute));

        Geschenkidee::aktualisieren($id, $personId, 'Idee für Tim', null, null, [], true);

        // Tims Geburtstag (1.1.) ist am 1.6.2026 schon vorbei, also 2027.
        $this->assertContains('Geburtstag Tim - 01.01.2027', Geschenkidee::anlassNamenInklGeburtstag($id, $heute));
    }

    public function testFestMachenSetztAnlassUndDatumUndUeberschreibtDieAnzeige(): void
    {
        $inZweiMonaten = (new DateTimeImmutable('today'))->modify('+2 months')->format('Y-m-d');
        $inDreiMonaten = (new DateTimeImmutable('today'))->modify('+3 months')->format('Y-m-d');

        $personId = $this->testPersonAnlegen('Max');
        Anlass::erstellen('Hochzeit', $inZweiMonaten, false, []);
        Anlass::erstellen('Jubiläum', $inDreiMonaten, false, []);
        $hochzeitId = (int) $this->findeAnlassNachName('Hochzeit')['id'];
        $jubilaeumId = (int) $this->findeAnlassNachName('Jubiläum')['id'];

        // Idee lose fuer beide Anlaesse getaggt UND fuer den Geburtstag markiert.
        Geschenkidee::erstellen($personId, 'Mehrfach-Kandidat', null, null, [$hochzeitId, $jubilaeumId], true);
        $id = (int) $this->findeIdeeNachText('Mehrfach-Kandidat')['id'];

        Geschenkidee::festMachen($id, $hochzeitId);

        $aktualisiert = Geschenkidee::finden($id);
        $this->assertSame($hochzeitId, (int) $aktualisiert['geschenk_anlass_id']);
        $this->assertSame($inZweiMonaten, $aktualisiert['geschenk_datum']);

        // Fest: nur noch der feste Anlass wird angezeigt, die losen Tags bleiben aber gespeichert.
        $erwartetesDatum = (new DateTimeImmutable($inZweiMonaten))->format('d.m.Y');
        $this->assertSame(['Hochzeit - ' . $erwartetesDatum], Geschenkidee::anlassNamenInklGeburtstag($id));
    }

    public function testZurueckAufOffenBringtLoseTagsZurueck(): void
    {
        $inZweiMonaten = (new DateTimeImmutable('today'))->modify('+2 months')->format('Y-m-d');

        $personId = $this->testPersonAnlegen('Anna');
        Anlass::erstellen('Hochzeit', $inZweiMonaten, false, []);
        $hochzeitId = (int) $this->findeAnlassNachName('Hochzeit')['id'];

        Geschenkidee::erstellen($personId, 'Idee', null, null, [$hochzeitId], true);
        $id = (int) $this->findeIdeeNachText('Idee')['id'];

        Geschenkidee::festMachen($id, $hochzeitId);
        $erwartetesDatum = (new DateTimeImmutable($inZweiMonaten))->format('d.m.Y');
        $this->assertSame(['Hochzeit - ' . $erwartetesDatum], Geschenkidee::anlassNamenInklGeburtstag($id));

        Geschenkidee::zurueckAufOffen($id);

        $aktualisiert = Geschenkidee::finden($id);
        $this->assertNull($aktualisiert['geschenk_anlass_id']);
        $this->assertNull($aktualisiert['geschenk_datum']);
        // Die losen Tags tauchen wieder auf. Festes $heute, damit das Ergebnis nicht vom Testdatum abhaengt.
        $heute = new DateTimeImmutable('2020-06-01');
        $namen = Geschenkidee::anlassNamenInklGeburtstag($id, $heute);
        $this->assertContains('Hochzeit - ' . $erwartetesDatum, $namen);
        $this->assertContains('Geburtstag Anna - 01.01.2021', $namen);
    }

    public function testAnlassNamenInklGeburtstagZeigtVergangenBeiFestemVergangenemDatum(): void
    {
        $personId = $this->testPersonAnlegen('Max');
        Anlass::erstellen('Vergangene Feier', '2020-01-01', false, []);
        $anlassId = (int) $this->findeAnlassNachName('Vergangene Feier')['id'];

        Geschenkidee::erstellen($personId, 'Idee', null, null);
        $id = (int) $this->findeIdeeNachText('Idee')['id'];

        // Vergangenes Datum direkt gesetzt, um die Anzeige fuer vergangene Geschenke zu testen.
        Geschenkidee::festMachen($id, $anlassId);

        $this->assertSame(['Vergangene Feier - 01.01.2020 (vergangen)'], Geschenkidee::anlassNamenInklGeburtstag($id));
    }

    public function testIstGueltigerZielAnlassLehntVergangeneEinmaligeAnlaesseAb(): void
    {
        $heute = new DateTimeImmutable('2026-06-01');
        $vergangenerAnlass = ['datum' => '2026-01-01', 'wiederholt_jaehrlich' => 0];

        $this->assertFalse(Geschenkidee::istGueltigerZielAnlass($vergangenerAnlass, $heute));
    }

    public function testIstGueltigerZielAnlassAkzeptiertWiederkehrendeUndZukuenftigeAnlaesse(): void
    {
        $heute = new DateTimeImmutable('2026-06-01');
        $wiederkehrenderAnlass = ['datum' => '2020-01-01', 'wiederholt_jaehrlich' => 1];
        $zukuenftigerAnlass = ['datum' => '2026-12-24', 'wiederholt_jaehrlich' => 0];

        $this->assertTrue(Geschenkidee::istGueltigerZielAnlass($wiederkehrenderAnlass, $heute));
        $this->assertTrue(Geschenkidee::istGueltigerZielAnlass($zukuenftigerAnlass, $heute));
    }

    public function testAnlassNamenInklGeburtstagBlendetVergangeneLoseEinmaligeAnlaesseAus(): void
    {
        $personId = $this->testPersonAnlegen('Max');
        Anlass::erstellen('Vergangener Anlass', '2020-01-01', false, []);
        Anlass::erstellen('Zukünftiger Anlass', '2099-01-01', false, []);
        $vergangenerId = (int) $this->findeAnlassNachName('Vergangener Anlass')['id'];
        $zukuenftigerId = (int) $this->findeAnlassNachName('Zukünftiger Anlass')['id'];

        Geschenkidee::erstellen($personId, 'Idee', null, null, [$vergangenerId, $zukuenftigerId]);
        $id = (int) $this->findeIdeeNachText('Idee')['id'];

        // Nicht geloescht, nur ausgeblendet: die Verknuepfung existiert weiterhin in der DB.
        $alleVerknuepften = array_column(Geschenkidee::anlaesse($id), 'name');
        $this->assertContains('Vergangener Anlass', $alleVerknuepften);

        $angezeigt = Geschenkidee::anlassNamenInklGeburtstag($id);
        $this->assertSame(['Zukünftiger Anlass - 01.01.2099'], $angezeigt);
    }

    public function testFestMachenFuerGeburtstagSetztFlagUndDatumUndUeberschreibtDieAnzeige(): void
    {
        Person::erstellen('Max', '1990-06-15', null, null);
        $personId = (int) $this->findePersonNachName('Max')['id'];
        $inZweiMonaten = (new DateTimeImmutable('today'))->modify('+2 months')->format('Y-m-d');
        Anlass::erstellen('Hochzeit', $inZweiMonaten, false, []);
        $hochzeitId = (int) $this->findeAnlassNachName('Hochzeit')['id'];

        Geschenkidee::erstellen($personId, 'Idee', null, null, [$hochzeitId]);
        $id = (int) $this->findeIdeeNachText('Idee')['id'];

        Geschenkidee::festMachenFuerGeburtstag($id);

        $aktualisiert = Geschenkidee::finden($id);
        $this->assertNull($aktualisiert['geschenk_anlass_id']);
        $this->assertSame(1, (int) $aktualisiert['geschenk_fuer_geburtstag']);
        $this->assertNotNull($aktualisiert['geschenk_datum']);

        $this->assertTrue(Geschenkidee::istFest($aktualisiert));
        // Nur der Geburtstag wird angezeigt, die Hochzeit bleibt gespeichert.
        $erwartetesDatum = (new DateTimeImmutable($aktualisiert['geschenk_datum']))->format('d.m.Y');
        $this->assertSame(['Geburtstag Max - ' . $erwartetesDatum], Geschenkidee::anlassNamenInklGeburtstag($id));
    }

    public function testFestMachenFuerGeburtstagUndFestMachenSchliessenSichGegenseitigAus(): void
    {
        $inZweiMonaten = (new DateTimeImmutable('today'))->modify('+2 months')->format('Y-m-d');
        Person::erstellen('Anna', '1985-03-20', null, null);
        $personId = (int) $this->findePersonNachName('Anna')['id'];
        Anlass::erstellen('Hochzeit', $inZweiMonaten, false, []);
        $hochzeitId = (int) $this->findeAnlassNachName('Hochzeit')['id'];

        Geschenkidee::erstellen($personId, 'Idee', null, null);
        $id = (int) $this->findeIdeeNachText('Idee')['id'];

        Geschenkidee::festMachenFuerGeburtstag($id);
        Geschenkidee::festMachen($id, $hochzeitId);

        $aktualisiert = Geschenkidee::finden($id);
        $this->assertSame($hochzeitId, (int) $aktualisiert['geschenk_anlass_id']);
        $this->assertSame(0, (int) $aktualisiert['geschenk_fuer_geburtstag']);
        $erwartetesDatum = (new DateTimeImmutable($inZweiMonaten))->format('d.m.Y');
        $this->assertSame(['Hochzeit - ' . $erwartetesDatum], Geschenkidee::anlassNamenInklGeburtstag($id));
    }

    public function testZurueckAufOffenSetztAuchGeburtstagsFestFlagZurueck(): void
    {
        Person::erstellen('Tim', '1990-01-01', null, null);
        $personId = (int) $this->findePersonNachName('Tim')['id'];
        Geschenkidee::erstellen($personId, 'Idee', null, null);
        $id = (int) $this->findeIdeeNachText('Idee')['id'];

        Geschenkidee::festMachenFuerGeburtstag($id);
        Geschenkidee::zurueckAufOffen($id);

        $aktualisiert = Geschenkidee::finden($id);
        $this->assertFalse(Geschenkidee::istFest($aktualisiert));
        $this->assertSame(0, (int) $aktualisiert['geschenk_fuer_geburtstag']);
        $this->assertNull($aktualisiert['geschenk_datum']);
    }

    public function testValidiereEingabeLiefertLeereListeBeiGueltigenWerten(): void
    {
        $person = ['id' => 1, 'name' => 'Anna'];

        $fehler = Geschenkidee::validiereEingabe($person, 'Ein Buch', '', '');

        $this->assertSame([], $fehler);
    }

    public function testValidiereEingabeLehntFehlendePersonUndFehlendenInhaltAb(): void
    {
        $fehler = Geschenkidee::validiereEingabe(null, '', '', '');

        $this->assertContains('Bitte eine Person auswählen.', $fehler);
        $this->assertContains('Bitte mindestens einen Inhalt angeben: Text, Link oder Bild.', $fehler);
    }

    public function testValidiereEingabeLehntUngueltigenLinkAb(): void
    {
        $person = ['id' => 1, 'name' => 'Anna'];

        $fehler = Geschenkidee::validiereEingabe($person, 'Text', 'kein-link', '');

        $this->assertContains('Bitte einen gültigen Link angeben (z. B. https://...).', $fehler);
    }

    public function testValidiereEingabeLehntZuLangeOffeneAufgabenAb(): void
    {
        $person = ['id' => 1, 'name' => 'Anna'];

        $fehler = Geschenkidee::validiereEingabe($person, 'Text', '', '', str_repeat('a', 1001));

        $this->assertContains(
            'Die offenen Aufgaben enthalten nicht erlaubte Inhalte oder sind zu lang (max. 1000 Zeichen).',
            $fehler
        );
    }

    public function testValidiereEingabeAkzeptiertLeereOffeneAufgaben(): void
    {
        $person = ['id' => 1, 'name' => 'Anna'];

        $fehler = Geschenkidee::validiereEingabe($person, 'Text', '', '');

        $this->assertSame([], $fehler);
    }

    public function testSortiereNachStatusTrenntInDreiRubriken(): void
    {
        $heute = new DateTimeImmutable('2026-09-12');
        $personId = $this->testPersonAnlegen('Max');

        Anlass::erstellen('Vergangene Feier', '2020-01-01', false, []);
        $vergangenerAnlassId = (int) $this->findeAnlassNachName('Vergangene Feier')['id'];
        Anlass::erstellen('Kommende Feier', '2026-12-24', false, []);
        $kommendeAnlassId = (int) $this->findeAnlassNachName('Kommende Feier')['id'];

        Geschenkidee::erstellen($personId, 'Noch offen', null, null);
        Geschenkidee::erstellen($personId, 'Fest fuer die Zukunft', null, null);
        Geschenkidee::erstellen($personId, 'Bereits verschenkt', null, null);
        $offeneId = (int) $this->findeIdeeNachText('Noch offen')['id'];
        $festeId = (int) $this->findeIdeeNachText('Fest fuer die Zukunft')['id'];
        $verschenkteId = (int) $this->findeIdeeNachText('Bereits verschenkt')['id'];

        Geschenkidee::festMachen($festeId, $kommendeAnlassId);
        // Direkt aufgerufen, um einen vergangenen Datensatz zu erzeugen.
        Geschenkidee::festMachen($verschenkteId, $vergangenerAnlassId);

        $sortiert = Geschenkidee::sortiereNachStatus(Geschenkidee::vonPerson($personId), $heute);

        $this->assertCount(1, $sortiert['offen']);
        $this->assertSame($offeneId, (int) $sortiert['offen'][0]['id']);
        $this->assertCount(1, $sortiert['fest']);
        $this->assertSame($festeId, (int) $sortiert['fest'][0]['id']);
        $this->assertCount(1, $sortiert['vergangen']);
        $this->assertSame($verschenkteId, (int) $sortiert['vergangen'][0]['id']);
    }

    public function testBekannteIdeenFuerGeburtstagEnthaeltLoseMarkierteUndNichtVergangeneFesteIdeen(): void
    {
        $heute = new DateTimeImmutable('2026-09-12');
        Person::erstellen('Anna', '1990-10-05', null, null);
        $personId = (int) $this->findePersonNachName('Anna')['id'];

        Geschenkidee::erstellen($personId, 'Lose fuer Geburtstag', null, null, [], true);
        Geschenkidee::erstellen($personId, 'Nicht fuer Geburtstag', null, null, [], false);
        Geschenkidee::erstellen($personId, 'Fest fuer kommenden Geburtstag', null, null);
        $festeId = (int) $this->findeIdeeNachText('Fest fuer kommenden Geburtstag')['id'];
        Geschenkidee::festMachenFuerGeburtstag($festeId);

        $bekannt = array_column(Geschenkidee::bekannteIdeenFuerGeburtstag($personId, $heute), 'text');

        $this->assertContains('Lose fuer Geburtstag', $bekannt);
        $this->assertContains('Fest fuer kommenden Geburtstag', $bekannt);
        $this->assertNotContains('Nicht fuer Geburtstag', $bekannt);
    }

    public function testBekannteIdeenFuerGeburtstagBlendetBereitsVergangeneFesteIdeenAus(): void
    {
        Person::erstellen('Max', '1990-06-15', null, null);
        $personId = (int) $this->findePersonNachName('Max')['id'];

        Geschenkidee::erstellen($personId, 'Letztes Jahr verschenkt', null, null);
        $id = (int) $this->findeIdeeNachText('Letztes Jahr verschenkt')['id'];
        Geschenkidee::festMachenFuerGeburtstag($id);

        // Ein Jahr spaeter: der Geburtstag ist vorbei und zaehlt nicht mehr.
        $einJahrSpaeter = (new DateTimeImmutable(Geschenkidee::finden($id)['geschenk_datum']))->modify('+1 year');

        $bekannt = Geschenkidee::bekannteIdeenFuerGeburtstag($personId, $einJahrSpaeter);

        $this->assertSame([], $bekannt);
    }

    public function testVergangenMachenSetztFelderBeiGueltigemVergangenemDatum(): void
    {
        $gestern = (new DateTimeImmutable('yesterday'))->format('Y-m-d');
        $personId = $this->testPersonAnlegen('Max');
        Anlass::erstellen('Hochzeit', '2026-12-24', false, []);
        $anlassId = (int) $this->findeAnlassNachName('Hochzeit')['id'];

        Geschenkidee::erstellen($personId, 'Idee', null, null);
        $id = (int) $this->findeIdeeNachText('Idee')['id'];

        $erfolg = Geschenkidee::vergangenMachen($id, $anlassId, $gestern);

        $this->assertTrue($erfolg);
        $aktualisiert = Geschenkidee::finden($id);
        $this->assertSame($anlassId, (int) $aktualisiert['geschenk_anlass_id']);
        $this->assertSame(0, (int) $aktualisiert['geschenk_fuer_geburtstag']);
        $this->assertSame($gestern, $aktualisiert['geschenk_datum']);
        $this->assertSame(1, (int) $aktualisiert['besorgt']);
    }

    public function testVergangenMachenLehntZukuenftigesDatumAb(): void
    {
        $morgen = (new DateTimeImmutable('tomorrow'))->format('Y-m-d');
        $personId = $this->testPersonAnlegen('Max');
        Anlass::erstellen('Hochzeit', '2026-12-24', false, []);
        $anlassId = (int) $this->findeAnlassNachName('Hochzeit')['id'];

        Geschenkidee::erstellen($personId, 'Idee', null, null);
        $id = (int) $this->findeIdeeNachText('Idee')['id'];

        $this->assertFalse(Geschenkidee::vergangenMachen($id, $anlassId, $morgen));
        $this->assertNull(Geschenkidee::finden($id)['geschenk_anlass_id']);
    }

    public function testVergangenMachenLehntUngueltigesDatumUndUnbekanntenAnlassAb(): void
    {
        $gestern = (new DateTimeImmutable('yesterday'))->format('Y-m-d');
        $personId = $this->testPersonAnlegen('Max');
        Anlass::erstellen('Hochzeit', '2026-12-24', false, []);
        $anlassId = (int) $this->findeAnlassNachName('Hochzeit')['id'];

        Geschenkidee::erstellen($personId, 'Idee', null, null);
        $id = (int) $this->findeIdeeNachText('Idee')['id'];

        // Kalendarisch ungueltiges Datum (30. Februar gibt es nicht).
        $this->assertFalse(Geschenkidee::vergangenMachen($id, $anlassId, '2026-02-30'));
        // Anlass existiert nicht.
        $this->assertFalse(Geschenkidee::vergangenMachen($id, 999999, $gestern));
    }

    public function testVergangenMachenFuerGeburtstagSetztFelderBeiGueltigemVergangenemDatum(): void
    {
        $gestern = (new DateTimeImmutable('yesterday'))->format('Y-m-d');
        $personId = $this->testPersonAnlegen('Max');

        Geschenkidee::erstellen($personId, 'Idee', null, null);
        $id = (int) $this->findeIdeeNachText('Idee')['id'];

        $erfolg = Geschenkidee::vergangenMachenFuerGeburtstag($id, $gestern);

        $this->assertTrue($erfolg);
        $aktualisiert = Geschenkidee::finden($id);
        $this->assertNull($aktualisiert['geschenk_anlass_id']);
        $this->assertSame(1, (int) $aktualisiert['geschenk_fuer_geburtstag']);
        $this->assertSame($gestern, $aktualisiert['geschenk_datum']);
        $this->assertSame(1, (int) $aktualisiert['besorgt']);
    }

    public function testVergangenMachenFuerGeburtstagLehntZukuenftigesDatumAb(): void
    {
        $morgen = (new DateTimeImmutable('tomorrow'))->format('Y-m-d');
        $personId = $this->testPersonAnlegen('Max');

        Geschenkidee::erstellen($personId, 'Idee', null, null);
        $id = (int) $this->findeIdeeNachText('Idee')['id'];

        $this->assertFalse(Geschenkidee::vergangenMachenFuerGeburtstag($id, $morgen));
        $this->assertSame(0, (int) Geschenkidee::finden($id)['geschenk_fuer_geburtstag']);
    }

    public function testStatusNachPersonFuerAnlassGruppiertLoseUndFesteIdeenProPerson(): void
    {
        Anlass::erstellen('Weihnachten-Test', '2026-12-24', true, []);
        $anlassId = (int) $this->findeAnlassNachName('Weihnachten-Test')['id'];

        $annaId = $this->testPersonAnlegen('Anna');
        Geschenkidee::erstellen($annaId, 'Buch', null, null, [$anlassId], false, true);
        Geschenkidee::erstellen($annaId, 'Spiel', null, null, [$anlassId], false, false, 'Noch einpacken');

        $maxId = $this->testPersonAnlegen('Max');
        Geschenkidee::erstellen($maxId, 'Schal', null, null);
        $schalId = (int) $this->findeIdeeNachText('Schal')['id'];
        Geschenkidee::festMachen($schalId, $anlassId);

        $status = Geschenkidee::statusNachPersonFuerAnlass($anlassId, new DateTimeImmutable('2026-06-01'));
        $statusNachName = [];
        foreach ($status as $eintrag) {
            $statusNachName[$eintrag['person']] = $eintrag;
        }

        $this->assertSame(2, $statusNachName['Anna']['ideen']);
        $this->assertSame(1, $statusNachName['Anna']['besorgt']);
        $this->assertSame(['Noch einpacken'], $statusNachName['Anna']['offene_aufgaben']);
        $this->assertSame(1, $statusNachName['Max']['ideen']);
        $this->assertSame(0, $statusNachName['Max']['besorgt']);
    }

    public function testStatusNachPersonFuerAnlassIgnoriertIdeenFuerAndereAnlaesse(): void
    {
        Anlass::erstellen('Weihnachten-Test', '2026-12-24', true, []);
        $weihnachtenId = (int) $this->findeAnlassNachName('Weihnachten-Test')['id'];
        Anlass::erstellen('Hochzeit', '2026-08-01', false, []);
        $hochzeitId = (int) $this->findeAnlassNachName('Hochzeit')['id'];

        $personId = $this->testPersonAnlegen('Max');
        Geschenkidee::erstellen($personId, 'Nur fuer Hochzeit', null, null, [$hochzeitId]);

        $status = Geschenkidee::statusNachPersonFuerAnlass($weihnachtenId, new DateTimeImmutable('2026-06-01'));

        $this->assertSame([], $status);
    }

    public function testStatusNachPersonFuerAnlassBlendetBereitsVergangeneFesteZuordnungenAus(): void
    {
        $personId = $this->testPersonAnlegen('Max');
        Anlass::erstellen('Vergangene Feier', '2020-01-01', false, []);
        $anlassId = (int) $this->findeAnlassNachName('Vergangene Feier')['id'];

        Geschenkidee::erstellen($personId, 'Idee', null, null);
        $id = (int) $this->findeIdeeNachText('Idee')['id'];
        // Direkt aufgerufen, um eine vergangene feste Zuordnung zu erzeugen.
        Geschenkidee::festMachen($id, $anlassId);

        $status = Geschenkidee::statusNachPersonFuerAnlass($anlassId, new DateTimeImmutable('2026-01-01'));

        $this->assertSame([], $status);
    }

    public function testDatengrundlageFuerGenerierungEnthaeltVergangeneUndFesteVollstaendig(): void
    {
        $personId = $this->testPersonAnlegen('Max');
        $gestern = (new DateTimeImmutable('yesterday'))->format('Y-m-d');
        Anlass::erstellen('Kommender Anlass', '2099-01-01', false, []);
        $kommenderAnlassId = (int) $this->findeAnlassNachName('Kommender Anlass')['id'];

        Geschenkidee::erstellen($personId, 'Vergangene Idee', null, null);
        $vergangeneId = (int) $this->findeIdeeNachText('Vergangene Idee')['id'];
        Geschenkidee::vergangenMachenFuerGeburtstag($vergangeneId, $gestern);

        Geschenkidee::erstellen($personId, 'Feste Idee', null, null);
        $festeId = (int) $this->findeIdeeNachText('Feste Idee')['id'];
        Geschenkidee::festMachen($festeId, $kommenderAnlassId);

        $daten = Geschenkidee::datengrundlageFuerGenerierung($personId);

        $this->assertContains('Vergangene Idee', $daten);
        $this->assertContains('Feste Idee', $daten);
    }

    public function testDatengrundlageFuerGenerierungBegrenztOffeneIdeenAufFuenfNeueste(): void
    {
        $personId = $this->testPersonAnlegen('Max');

        for ($i = 1; $i <= 6; $i++) {
            Geschenkidee::erstellen($personId, "Offene Idee $i", null, null);
        }

        $daten = Geschenkidee::datengrundlageFuerGenerierung($personId);

        $this->assertCount(5, $daten);
        // Gleiche Sekunde angelegt: es zaehlt nur, dass es genau fuenf sind.
        $this->assertCount(5, array_intersect(
            $daten,
            ['Offene Idee 1', 'Offene Idee 2', 'Offene Idee 3', 'Offene Idee 4', 'Offene Idee 5', 'Offene Idee 6']
        ));
    }

    public function testDatengrundlageFuerGenerierungIstLeerOhneGeschenkideen(): void
    {
        $personId = $this->testPersonAnlegen('Max');

        $this->assertSame([], Geschenkidee::datengrundlageFuerGenerierung($personId));
    }

    public function testDatengrundlageFuerGenerierungUeberspringtIdeenOhneText(): void
    {
        $personId = $this->testPersonAnlegen('Max');
        Geschenkidee::erstellen($personId, null, 'https://beispiel.de', null);

        $daten = Geschenkidee::datengrundlageFuerGenerierung($personId);

        $this->assertSame([], $daten);
    }

    public function testZusammenfassungFuerPflichtanlassGruppiertAlleDreiFaelle(): void
    {
        Anlass::erstellen('Weihnachten-Test', '2026-12-24', true, []);
        $anlassId = (int) $this->findeAnlassNachName('Weihnachten-Test')['id'];

        // Alles besorgt.
        $berta = $this->testPersonAnlegen('Berta');
        Geschenkidee::erstellen($berta, 'Buch', null, null, [$anlassId], false, true);
        // Eine von zwei Ideen besorgt -> noch offen.
        $anna = $this->testPersonAnlegen('Anna');
        Geschenkidee::erstellen($anna, 'Schal', null, null, [$anlassId], false, true);
        Geschenkidee::erstellen($anna, 'Tee', null, null, [$anlassId]);
        // Nur Ideen fuer einen anderen Anlass bzw. gar keine -> ohne Idee.
        $this->testPersonAnlegen('Zoe');
        $carl = $this->testPersonAnlegen('Carl');
        Geschenkidee::erstellen($carl, 'Ohne Anlass', null, null);

        $zusammenfassung = Geschenkidee::zusammenfassungFuerPflichtanlass($anlassId, new DateTimeImmutable('2026-06-01'));

        $this->assertSame(
            ['besorgt' => ['Berta'], 'offen' => ['Anna'], 'ohne_idee' => ['Carl', 'Zoe']],
            $zusammenfassung
        );
    }
}
