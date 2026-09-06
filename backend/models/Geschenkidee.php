<?php

require_once __DIR__ . '/../config/Datenbank.php';
require_once __DIR__ . '/Anlass.php';

class Geschenkidee
{
    /**
     * Statement-Schluesselwoerter fuer das Freitextfeld "text", analog zu
     * Person::enthaeltSqlSchluesselwort() - zweite Verteidigungslinie
     * zusaetzlich zu den parametrisierten Queries unten (Anforderung:
     * "Eingabefelder duerfen keine SQL-Schluesselwoerter akzeptieren").
     * ALTER und UNION bewusst nicht enthalten - beides sind zu gebraeuchliche
     * Alltagswoerter ("Alter" = Lebensalter, "Union" z. B. in Buch-/Filmtiteln),
     * die hier staendig faelschlich abgelehnt wuerden. Die eigentliche
     * Injection-Verhinderung leisten ohnehin die parametrisierten Queries.
     */
    private const SQL_SCHLUESSELWOERTER = [
        'SELECT', 'INSERT', 'UPDATE', 'DELETE', 'DROP', 'EXEC',
        'TRUNCATE', 'CREATE TABLE',
    ];

    private const SQL_SONDERZEICHEN = ['--', ';', '/*', '*/'];

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
     */
    public static function erstellen(int $personId, ?string $text, ?string $link, ?string $bildLink, array $anlassIds = []): void
    {
        $pdo = Datenbank::verbinden();
        $stmt = $pdo->prepare(
            'INSERT INTO geschenkideen (person_id, text, link, bild_link)
             VALUES (:person_id, :text, :link, :bild_link)'
        );
        $stmt->execute([
            'person_id' => $personId,
            'text' => $text,
            'link' => $link,
            'bild_link' => $bildLink,
        ]);

        self::anlaesseVerknuepfen($pdo, (int) $pdo->lastInsertId(), $anlassIds);
    }

    /**
     * @param int[] $anlassIds
     */
    public static function aktualisieren(int $id, int $personId, ?string $text, ?string $link, ?string $bildLink, array $anlassIds = []): void
    {
        $pdo = Datenbank::verbinden();
        $stmt = $pdo->prepare(
            'UPDATE geschenkideen
             SET person_id = :person_id, text = :text, link = :link, bild_link = :bild_link
             WHERE id = :id'
        );
        $stmt->execute([
            'person_id' => $personId,
            'text' => $text,
            'link' => $link,
            'bild_link' => $bildLink,
            'id' => $id,
        ]);

        self::anlaesseVerknuepfen($pdo, $id, $anlassIds);
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
        $pdo->prepare('DELETE FROM geschenkidee_anlaesse WHERE geschenkidee_id = :geschenkidee_id')
            ->execute(['geschenkidee_id' => $geschenkideeId]);

        $stmt = $pdo->prepare(
            'INSERT INTO geschenkidee_anlaesse (geschenkidee_id, anlass_id) VALUES (:geschenkidee_id, :anlass_id)'
        );
        foreach (array_unique($anlassIds) as $anlassId) {
            $stmt->execute(['geschenkidee_id' => $geschenkideeId, 'anlass_id' => $anlassId]);
        }
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
        return $text === '' || (strlen($text) <= 1000 && !self::enthaeltSqlSchluesselwort($text));
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
     * Sucht die Schluesselwoerter als eigenstaendige Woerter (\b-Wortgrenzen), nicht als
     * blosse Teilzeichenkette - sonst wuerden z. B. "Dropbox" oder "Selection" faelschlich
     * abgelehnt, obwohl sie SELECT/DROP nur als Teil eines laengeren Wortes enthalten.
     */
    public static function enthaeltSqlSchluesselwort(string $eingabe): bool
    {
        foreach (self::SQL_SCHLUESSELWOERTER as $schluesselwort) {
            if (preg_match('/\b' . preg_quote($schluesselwort, '/') . '\b/i', $eingabe) === 1) {
                return true;
            }
        }

        foreach (self::SQL_SONDERZEICHEN as $zeichen) {
            if (str_contains($eingabe, $zeichen)) {
                return true;
            }
        }

        return false;
    }
}
