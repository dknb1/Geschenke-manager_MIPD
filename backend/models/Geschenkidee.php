<?php

require_once __DIR__ . '/../config/Datenbank.php';
require_once __DIR__ . '/Anlass.php';
require_once __DIR__ . '/Person.php';
require_once __DIR__ . '/SqlDenylist.php';

class Geschenkidee
{
    public static function alle(): array
    {
        $pdo = Datenbank::verbinden();
        $stmt = $pdo->query(
            'SELECT geschenkideen.*, personen.name AS person_name
             FROM geschenkideen
             JOIN personen ON personen.id = geschenkideen.person_id
             ORDER BY geschenkideen.erstellt_am DESC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function finden(int $id): ?array
    {
        $pdo = Datenbank::verbinden();
        $stmt = $pdo->prepare('SELECT * FROM geschenkideen WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $idee = $stmt->fetch(PDO::FETCH_ASSOC);
        return $idee !== false ? $idee : null;
    }

    public static function vonPerson(int $personId): array
    {
        $pdo = Datenbank::verbinden();
        $stmt = $pdo->prepare(
            'SELECT * FROM geschenkideen WHERE person_id = :person_id ORDER BY erstellt_am DESC'
        );
        $stmt->execute(['person_id' => $personId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param int[] $anlassIds IDs der Anlaesse, zu denen diese Idee passt (kann leer sein -
     *                         eine Idee muss nicht zwingend einem Anlass zugeordnet sein)
     * @param bool $fuerGeburtstag Ob die Idee (zusaetzlich) fuer den Geburtstag der Person
     *                             gedacht ist - explizit statt automatisch, da eine Idee auch
     *                             ausschliesslich fuer einen anderen Anlass (z. B. Hochzeit)
     *                             oder ganz ohne Anlass gedacht sein kann
     */
    public static function erstellen(
    int $personId,
    ?string $text,
    ?string $link,
    ?string $bildLink,
    array $anlassIds = [],
    bool $fuerGeburtstag = false,
    bool $besorgt = false,
    ?string $offeneAufgaben = null
): void
{
    $pdo = Datenbank::verbinden();

    $stmt = $pdo->prepare(
        'INSERT INTO geschenkideen (
            person_id,
            text,
            link,
            bild_link,
            fuer_geburtstag,
            besorgt,
            offene_aufgaben
        )
        VALUES (
            :person_id,
            :text,
            :link,
            :bild_link,
            :fuer_geburtstag,
            :besorgt,
            :offene_aufgaben
        )'
    );

    $stmt->execute([
        'person_id' => $personId,
        'text' => $text,
        'link' => $link,
        'bild_link' => $bildLink,
        'fuer_geburtstag' => $fuerGeburtstag ? 1 : 0,
        'besorgt' => $besorgt ? 1 : 0,
        'offene_aufgaben' => $offeneAufgaben,
    ]);

    self::anlaesseVerknuepfen(
        $pdo,
        (int) $pdo->lastInsertId(),
        $anlassIds
    );
}

    /**
     * @param int[] $anlassIds
     */
public static function aktualisieren(
    int $id,
    int $personId,
    ?string $text,
    ?string $link,
    ?string $bildLink,
    array $anlassIds = [],
    bool $fuerGeburtstag = false,
    bool $besorgt = false,
    ?string $offeneAufgaben = null
): void
{
    $pdo = Datenbank::verbinden();

    $stmt = $pdo->prepare(
        'UPDATE geschenkideen
         SET person_id = :person_id,
             text = :text,
             link = :link,
             bild_link = :bild_link,
             fuer_geburtstag = :fuer_geburtstag,
             besorgt = :besorgt,
             offene_aufgaben = :offene_aufgaben
         WHERE id = :id'
    );

    $stmt->execute([
        'person_id' => $personId,
        'text' => $text,
        'link' => $link,
        'bild_link' => $bildLink,
        'fuer_geburtstag' => $fuerGeburtstag ? 1 : 0,
        'besorgt' => $besorgt ? 1 : 0,
        'offene_aufgaben' => $offeneAufgaben,
        'id' => $id,
    ]);

    self::anlaesseVerknuepfen(
        $pdo,
        $id,
        $anlassIds
    );
}
    /**
     * Wandelt die Idee in ein "festes" Geschenk fuer $anlassId um (Idee->Geschenk-Umwandlung,
     * Aufgabenstellung: "inkl. Anlass und Datum"). Das Datum wird HIER eingefroren
     * (Anlass::naechstesVorkommen() zum jetzigen Zeitpunkt), nicht live nachberechnet -
     * sonst koennte ein wiederkehrender Anlass (dessen naechstesVorkommen() nie in der
     * Vergangenheit liegt) niemals als "vergangen" erkannt werden. Vorausgesetzt wird, dass
     * der Aufrufer bereits mit istGueltigerZielAnlass() geprueft hat, dass $anlassId existiert
     * und nicht in der Vergangenheit liegt. Setzt geschenk_fuer_geburtstag zurueck, falls die
     * Idee vorher fest fuer den Geburtstag war - eine Idee ist immer nur fuer GENAU ein Ziel
     * fest zugeordnet.
     */
    public static function festMachen(int $id, int $anlassId): void
    {
        $anlass = Anlass::finden($anlassId);
        if ($anlass === null) {
            return;
        }

        $pdo = Datenbank::verbinden();
        $stmt = $pdo->prepare(
            'UPDATE geschenkideen
             SET geschenk_anlass_id = :anlass_id, geschenk_fuer_geburtstag = 0,
                 geschenk_datum = :geschenk_datum
             WHERE id = :id'
        );
        $stmt->execute([
            'anlass_id' => $anlassId,
            'geschenk_datum' => Anlass::naechstesVorkommen($anlass)->format('Y-m-d'),
            'id' => $id,
        ]);
    }

    /**
     * Wandelt die Idee in ein "festes" Geschenk fuer den Geburtstag der zugehoerigen Person
     * um - Gegenstueck zu festMachen() fuer den Fall, dass der Geburtstag selbst (nicht ein
     * Eintrag aus anlaesse) das Ziel ist. Braucht eine eigene Methode statt eines Aufrufs von
     * festMachen() mit einer Geburtstag-"ID", weil der Geburtstag keine echte anlaesse-Zeile
     * und damit keine gueltige anlass_id hat (siehe Schema-Kommentar).
     */
    public static function festMachenFuerGeburtstag(int $id): void
    {
        $idee = self::finden($id);
        if ($idee === null) {
            return;
        }

        $person = Person::finden((int) $idee['person_id']);
        if ($person === null) {
            return;
        }

        $pdo = Datenbank::verbinden();
        $stmt = $pdo->prepare(
            'UPDATE geschenkideen
             SET geschenk_anlass_id = NULL, geschenk_fuer_geburtstag = 1,
                 geschenk_datum = :geschenk_datum
             WHERE id = :id'
        );
        $stmt->execute([
            'geschenk_datum' => Anlass::naechstesVorkommen(Person::geburtstagAlsAnlass($person))->format('Y-m-d'),
            'id' => $id,
        ]);
    }
    public static function vergangenMachen(int $id, int $anlassId, string $datum): bool
{
    $anlass = Anlass::finden($anlassId);
    $geschenkDatum = DateTimeImmutable::createFromFormat('Y-m-d', $datum);

    if ($anlass === null
        || $geschenkDatum === false
        || $geschenkDatum->format('Y-m-d') !== $datum
        || $geschenkDatum >= new DateTimeImmutable('today')) {
        return false;
    }

    $pdo = Datenbank::verbinden();
    $stmt = $pdo->prepare(
        'UPDATE geschenkideen
         SET geschenk_anlass_id = :anlass_id,
             geschenk_fuer_geburtstag = 0,
             geschenk_datum = :geschenk_datum,
             besorgt = 1
         WHERE id = :id'
    );

    $stmt->execute([
        'anlass_id' => $anlassId,
        'geschenk_datum' => $datum,
        'id' => $id,
    ]);

    return true;
}

public static function vergangenMachenFuerGeburtstag(int $id, string $datum): bool
{
    $idee = self::finden($id);
    $geschenkDatum = DateTimeImmutable::createFromFormat('Y-m-d', $datum);

    if ($idee === null
        || $geschenkDatum === false
        || $geschenkDatum->format('Y-m-d') !== $datum
        || $geschenkDatum >= new DateTimeImmutable('today')) {
        return false;
    }

    $person = Person::finden((int) $idee['person_id']);

    if ($person === null) {
        return false;
    }

    $pdo = Datenbank::verbinden();
    $stmt = $pdo->prepare(
        'UPDATE geschenkideen
         SET geschenk_anlass_id = NULL,
             geschenk_fuer_geburtstag = 1,
             geschenk_datum = :geschenk_datum,
             besorgt = 1
         WHERE id = :id'
    );

    $stmt->execute([
        'geschenk_datum' => $datum,
        'id' => $id,
    ]);

    return true;
}

    /**
     * Macht eine feste Geschenk-Zuordnung rueckgaengig - die Idee gilt danach wieder als
     * "offen". Die losen Anlass-Tags (anlaesse()/fuer_geburtstag) werden davon nicht
     * beruehrt, da sie waehrend der Fest-Zuordnung nicht veraendert wurden (siehe
     * anlassNamenInklGeburtstag() - sie tauchen in der Anzeige automatisch wieder auf).
     */
    public static function zurueckAufOffen(int $id): void
    {
        $pdo = Datenbank::verbinden();
        $stmt = $pdo->prepare(
            'UPDATE geschenkideen
             SET geschenk_anlass_id = NULL, geschenk_fuer_geburtstag = 0, geschenk_datum = NULL
             WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
    }

    /**
     * Ist diese Idee ueberhaupt fest zugeordnet (zu einem Anlass ODER zum Geburtstag)?
     * Zentrale Stelle fuer diese Pruefung, damit Aufrufer (Frontend, anlassNamenInklGeburtstag())
     * nicht jeweils beide Felder einzeln abfragen muessen.
     */
    public static function istFest(array $idee): bool
    {
        return $idee['geschenk_anlass_id'] !== null || (int) $idee['geschenk_fuer_geburtstag'] === 1;
    }

    /**
     * Ein Anlass darf nur dann fest zugeordnet werden, wenn sein naechstes Vorkommen noch
     * nicht vergangen ist - man soll keine Geschenke rueckwirkend fuer die Vergangenheit
     * "planen". Bei wiederkehrenden Anlaessen ist das durch naechstesVorkommen() ohnehin
     * immer erfuellt, bei einmaligen, bereits vergangenen Anlaessen greift die Sperre.
     */
    public static function istGueltigerZielAnlass(array $anlass, ?DateTimeImmutable $heute = null): bool
    {
        $heute ??= new DateTimeImmutable('today');
        return Anlass::naechstesVorkommen($anlass, $heute) >= $heute;
    }

    public static function loeschen(int $id): bool
    {
        if (self::finden($id) === null) {
            return false;
        }

        $pdo = Datenbank::verbinden();
        $stmt = $pdo->prepare('DELETE FROM geschenkideen WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return true;
    }

    /**
     * Ersetzt die komplette Anlass-Verknuepfung einer Idee durch $anlassIds (loeschen + neu
     * anlegen statt Diff, analog zu Anlass::personenVerknuepfen() - die Mengen im
     * Prototyp-Umfang sind klein).
     *
     * @param int[] $anlassIds
     */
    private static function anlaesseVerknuepfen(PDO $pdo, int $geschenkideeId, array $anlassIds): void
    {
        Datenbank::ersetzeVerknuepfung($pdo, 'geschenkidee_anlaesse', 'geschenkidee_id', $geschenkideeId, 'anlass_id', $anlassIds);
    }

    /**
     * Alle Anlaesse, denen diese Idee zugeordnet ist, sortiert nach naechstem Vorkommen
     * (nutzt Anlass::naechstesVorkommen(), analog zu Anlass::vonPerson()).
     */
    public static function anlaesse(int $geschenkideeId): array
    {
        $pdo = Datenbank::verbinden();
        $stmt = $pdo->prepare(
            'SELECT anlaesse.*
             FROM geschenkidee_anlaesse
             JOIN anlaesse ON anlaesse.id = geschenkidee_anlaesse.anlass_id
             WHERE geschenkidee_anlaesse.geschenkidee_id = :geschenkidee_id'
        );
        $stmt->execute(['geschenkidee_id' => $geschenkideeId]);
        $anlaesse = $stmt->fetchAll(PDO::FETCH_ASSOC);

        usort(
            $anlaesse,
            fn (array $a, array $b) => Anlass::naechstesVorkommen($a) <=> Anlass::naechstesVorkommen($b)
        );

        return $anlaesse;
    }

    /**
     * Namen aller Anlaesse, die zu dieser Idee passen - fuer die Anzeige (Ideenlisten).
     *
     * Ist die Idee fest zugeordnet (siehe istFest() - entweder zu einem Anlass oder zum
     * Geburtstag), wird NUR dieses eine Ziel gezeigt (mit "- vergangen"-Zusatz, falls
     * geschenk_datum bereits vorbei ist) - die losen Tags (anlaesse()/fuer_geburtstag) werden
     * dann bewusst ausgeblendet, nicht geloescht: macht man die Fest-Zuordnung rueckgaengig
     * (zurueckAufOffen()), tauchen sie hier automatisch wieder auf.
     *
     * Ist die Idee noch offen, werden die losen Verknuepfungen gezeigt: alle explizit
     * verknuepften Anlaesse (siehe anlaesse()), deren naechstes Vorkommen noch nicht vergangen
     * ist (ein bereits vergangener EINMALIGER Anlass wird hier nur ausgeblendet, nicht aus
     * geschenkidee_anlaesse geloescht - wiederkehrende sind durch naechstesVorkommen() ohnehin
     * nie "vergangen"), plus - nur falls fuer_geburtstag gesetzt ist - der Geburtstag der
     * Person. Eine Idee ist NICHT automatisch fuer den Geburtstag gedacht (kann z. B.
     * ausschliesslich ein Hochzeitsgeschenk oder ganz ohne Anlass sein), deshalb haengt das
     * hier vom explizit gesetzten Flag ab statt immer dabei zu sein.
     */
    public static function anlassNamenInklGeburtstag(int $geschenkideeId): array
    {
        $idee = self::finden($geschenkideeId);
        if ($idee === null) {
            return [];
        }

        if (self::istFest($idee)) {
            $istVergangen = new DateTimeImmutable($idee['geschenk_datum']) < new DateTimeImmutable('today');

            if ((int) $idee['geschenk_fuer_geburtstag'] === 1) {
                $person = Person::finden((int) $idee['person_id']);
                if ($person === null) {
                    return [];
                }
                $name = Person::geburtstagAlsAnlass($person)['name'];
            } else {
                $festerAnlass = Anlass::finden((int) $idee['geschenk_anlass_id']);
                if ($festerAnlass === null) {
                    return [];
                }
                $name = $festerAnlass['name'];
            }

            return [$name . ($istVergangen ? ' - vergangen' : '')];
        }

        $heute = new DateTimeImmutable('today');
        $namen = array_column(
            array_filter(
                self::anlaesse($geschenkideeId),
                fn (array $anlass) => Anlass::naechstesVorkommen($anlass, $heute) >= $heute
            ),
            'name'
        );

        if ((int) $idee['fuer_geburtstag'] === 1) {
            $person = Person::finden((int) $idee['person_id']);
            if ($person !== null) {
                array_unshift($namen, Person::geburtstagAlsAnlass($person)['name']);
            }
        }

        return $namen;
    }

    /**
     * Eine Idee muss laut Anforderung als Text, Link und/oder Bild angegeben
     * werden koennen - mindestens eine dieser drei Angaben ist noetig, damit
     * die Idee ueberhaupt einen Inhalt hat.
     */
    public static function hatInhalt(string $text, string $link, string $bildLink): bool
    {
        return $text !== '' || $link !== '' || $bildLink !== '';
    }

    public static function istGueltigerText(string $text): bool
    {
        return $text === '' || (strlen($text) <= 1000 && !SqlDenylist::enthaeltSchluesselwort($text));
    }

    /**
     * Gilt fuer "Link" und "Bild-Link" gleichermassen: beide sind einfache
     * URL-Felder, ein leerer Wert ist erlaubt (optional).
     */
    public static function istGueltigeUrl(string $url): bool
    {
        return $url === '' || (strlen($url) <= 2000 && filter_var($url, FILTER_VALIDATE_URL) !== false);
    }

    /**
     * Buendelt die Validierung von Person/Text/Link/Bild-Link fuer erstellen()/aktualisieren()
     * in einer Liste verstaendlicher Fehlermeldungen (leer = gueltig) - zentrale Stelle statt
     * dieselbe Pruefungs-/Fehlertext-Kette in idee-speichern.php und idee-bearbeiten.php
     * dupliziert zu pflegen.
     *
     * @param ?array $person Bereits aufgeloeste Person (Person::finden()) oder null, falls
     *                       keine/keine gueltige Person ausgewaehlt wurde
     */
    public static function validiereEingabe(?array $person, string $text, string $link, string $bildLink): array
    {
        $fehler = [];

        if ($person === null) {
            $fehler[] = 'Bitte eine Person auswählen.';
        }

        if (!self::hatInhalt($text, $link, $bildLink)) {
            $fehler[] = 'Bitte mindestens einen Inhalt angeben: Text, Link oder Bild.';
        }

        if (!self::istGueltigerText($text)) {
            $fehler[] = 'Die Idee enthält nicht erlaubte Inhalte oder ist zu lang (max. 1000 Zeichen).';
        }

        if (!self::istGueltigeUrl($link)) {
            $fehler[] = 'Bitte einen gültigen Link angeben (z. B. https://...).';
        }

        if (!self::istGueltigeUrl($bildLink)) {
            $fehler[] = 'Bitte einen gültigen Bild-Link angeben (z. B. https://...).';
        }

        return $fehler;
    }
}
