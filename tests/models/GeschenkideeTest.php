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
        // "Union" ist ein zu gebraeuchliches Alltagswort und deshalb nicht mehr in der
        // Denylist (siehe SqlDenylist). "UPDATE" bleibt dagegen
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

        // Sobald fest, zeigt die Anzeige NUR noch den festen Anlass - Jubilaeum und Geburtstag
        // verschwinden aus der Anzeige, obwohl die losen Tags in der DB unveraendert bleiben.
        $this->assertSame(['Hochzeit'], Geschenkidee::anlassNamenInklGeburtstag($id));
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
        $this->assertSame(['Hochzeit'], Geschenkidee::anlassNamenInklGeburtstag($id));

        Geschenkidee::zurueckAufOffen($id);

        $aktualisiert = Geschenkidee::finden($id);
        $this->assertNull($aktualisiert['geschenk_anlass_id']);
        $this->assertNull($aktualisiert['geschenk_datum']);
        // Die urspruenglichen losen Tags (Hochzeit + Geburtstag) sind nie geloescht worden und
        // tauchen jetzt automatisch wieder auf.
        $namen = Geschenkidee::anlassNamenInklGeburtstag($id);
        $this->assertContains('Hochzeit', $namen);
        $this->assertContains('Geburtstag Anna', $namen);
    }

    public function testAnlassNamenInklGeburtstagZeigtVergangenBeiFestemVergangenemDatum(): void
    {
        $personId = $this->testPersonAnlegen('Max');
        Anlass::erstellen('Vergangene Feier', '2020-01-01', false, []);
        $anlassId = (int) $this->findeAnlassNachName('Vergangene Feier')['id'];

        Geschenkidee::erstellen($personId, 'Idee', null, null);
        $id = (int) $this->findeIdeeNachText('Idee')['id'];

        // festMachen() wuerde ueber die Validierung in istGueltigerZielAnlass() normalerweise
        // nicht fuer einen vergangenen Anlass aufgerufen - hier direkt das Datum simuliert, um
        // die Anzeige-Logik fuer bereits vergangene feste Geschenke isoliert zu testen.
        Geschenkidee::festMachen($id, $anlassId);

        $this->assertSame(['Vergangene Feier - vergangen'], Geschenkidee::anlassNamenInklGeburtstag($id));
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
        $this->assertSame(['Zukünftiger Anlass'], $angezeigt);
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
        // Nur noch der Geburtstag wird angezeigt, die lose Hochzeit-Verknuepfung verschwindet
        // aus der Anzeige (bleibt aber in der DB, siehe zurueckAufOffen()-Test).
        $this->assertSame(['Geburtstag Max'], Geschenkidee::anlassNamenInklGeburtstag($id));
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
        $this->assertSame(['Hochzeit'], Geschenkidee::anlassNamenInklGeburtstag($id));
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
}
