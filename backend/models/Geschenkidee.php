<?php

require_once __DIR__ . '/../config/Datenbank.php';

class Geschenkidee
{
    /**
     * Statement-Schluesselwoerter fuer das Freitextfeld "text", analog zu
     * Person::enthaeltSqlSchluesselwort() - zweite Verteidigungslinie
     * zusaetzlich zu den parametrisierten Queries unten (Anforderung:
     * "Eingabefelder duerfen keine SQL-Schluesselwoerter akzeptieren").
     */
    private const SQL_SCHLUESSELWOERTER = [
        'SELECT', 'INSERT', 'UPDATE', 'DELETE', 'DROP', 'ALTER', 'UNION', 'EXEC',
        'TRUNCATE', 'CREATE TABLE', '--', ';', '/*', '*/',
    ];

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

    public static function vonPerson(int $personId): array
    {
        $pdo = Datenbank::verbinden();
        $stmt = $pdo->prepare(
            'SELECT * FROM geschenkideen WHERE person_id = :person_id ORDER BY erstellt_am DESC'
        );
        $stmt->execute(['person_id' => $personId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function erstellen(int $personId, ?string $text, ?string $link, ?string $bildLink): void
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

    public static function enthaeltSqlSchluesselwort(string $eingabe): bool
    {
        $obenGross = strtoupper($eingabe);

        foreach (self::SQL_SCHLUESSELWOERTER as $schluesselwort) {
            if (str_contains($obenGross, $schluesselwort)) {
                return true;
            }
        }

        return false;
    }
}
