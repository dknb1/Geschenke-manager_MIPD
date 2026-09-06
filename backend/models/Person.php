<?php

require_once __DIR__ . '/../config/Datenbank.php';

class Person
{
    private const ERLAUBTE_GESCHLECHTER = ['maennlich', 'weiblich', 'divers'];

    /**
     * Statement-Schluesselwoerter fuer die Freitext-Details. Name und Geschlecht werden
     * bereits per Allowlist/Enum geprueft, brauchen also keine zusaetzliche Denylist.
     * Dient als zweite Verteidigungslinie zusaetzlich zu den parametrisierten Queries
     * unten (Anforderung: "Eingabefelder duerfen keine SQL-Schluesselwoerter akzeptieren").
     */
    private const SQL_SCHLUESSELWOERTER = [
        'SELECT', 'INSERT', 'UPDATE', 'DELETE', 'DROP', 'ALTER', 'UNION', 'EXEC',
        'TRUNCATE', 'CREATE TABLE', '--', ';', '/*', '*/',
    ];

    public static function alle(): array
    {
        $pdo = Datenbank::verbinden();
        $stmt = $pdo->query('SELECT * FROM personen ORDER BY name COLLATE NOCASE');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function finden(int $id): ?array
    {
        $pdo = Datenbank::verbinden();
        $stmt = $pdo->prepare('SELECT * FROM personen WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $person = $stmt->fetch(PDO::FETCH_ASSOC);
        return $person !== false ? $person : null;
    }

    public static function erstellen(string $name, string $geburtsdatum, ?string $geschlecht, ?string $details): void
    {
        $pdo = Datenbank::verbinden();
        $stmt = $pdo->prepare(
            'INSERT INTO personen (name, geburtsdatum, geschlecht, details)
             VALUES (:name, :geburtsdatum, :geschlecht, :details)'
        );
        $stmt->execute([
            'name' => $name,
            'geburtsdatum' => $geburtsdatum,
            'geschlecht' => $geschlecht,
            'details' => $details,
        ]);
    }

    public static function aktualisieren(int $id, string $name, string $geburtsdatum, ?string $geschlecht, ?string $details): void
    {
        $pdo = Datenbank::verbinden();
        $stmt = $pdo->prepare(
            'UPDATE personen
             SET name = :name, geburtsdatum = :geburtsdatum, geschlecht = :geschlecht, details = :details
             WHERE id = :id'
        );
        $stmt->execute([
            'name' => $name,
            'geburtsdatum' => $geburtsdatum,
            'geschlecht' => $geschlecht,
            'details' => $details,
            'id' => $id,
        ]);
    }

    /**
     * Live berechnetes Alter aus dem Geburtsdatum, analog zu Anlass::naechstesVorkommen()
     * keine gespeicherte Spalte - ein statisch eingetragenes Alter wuerde sonst mit der Zeit
     * veralten, ohne dass es jemand nachtraegt. $heute ist fuer Tests injizierbar.
     */
    public static function alter(array $person, ?DateTimeImmutable $heute = null): int
    {
        $geburtsdatum = new DateTimeImmutable($person['geburtsdatum']);
        $heute ??= new DateTimeImmutable('today');

        return $heute->diff($geburtsdatum)->y;
    }

    /**
     * Repraesentiert den Geburtstag dieser Person als Anlass-foermiges Array (gleiche
     * 'datum'/'wiederholt_jaehrlich'-Struktur wie eine anlaesse-Zeile), damit er ueber
     * Anlass::naechstesVorkommen() berechnet und in der Anlassliste einsortiert werden kann -
     * ohne als eigene Zeile in der anlaesse-Tabelle dupliziert zu werden (siehe fachliche
     * Dokumentation, Abschnitt "Pflichtanlässe").
     */
    public static function geburtstagAlsAnlass(array $person): array
    {
        return [
            'id' => null,
            'name' => 'Geburtstag ' . $person['name'],
            'datum' => $person['geburtsdatum'],
            'wiederholt_jaehrlich' => 1,
            'geschuetzt' => 1,
            'person_name' => null,
            'ist_geburtstag' => true,
            'person_id' => (int) $person['id'],
        ];
    }

    public static function loeschen(int $id): bool
    {
        if (self::finden($id) === null) {
            return false;
        }

        $pdo = Datenbank::verbinden();
        $stmt = $pdo->prepare('DELETE FROM personen WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return true;
    }

    /**
     * Erlaubt Buchstaben (inkl. Umlaute), Leerzeichen, Bindestriche und Apostrophe,
     * damit z. B. "Anna-Lena" oder "O'Brien" moeglich sind, aber keine Zahlen,
     * Anfuehrungszeichen oder SQL-Sonderzeichen.
     */
    public static function istGueltigerName(string $name): bool
    {
        return (bool) preg_match('/^[\p{L}\s\'\-]{1,100}$/u', $name);
    }

    /**
     * Geburtsdatum ist Pflichtfeld (analog zu Weihnachten als "obligatorischer" Anlass,
     * siehe fachliche Dokumentation) - Leerstring wird deshalb hier bewusst NICHT akzeptiert,
     * die Pflichtfeldpruefung erfolgt separat mit eigener Fehlermeldung wie bei istGueltigerName().
     * Gueltiges Y-m-d-Datum, das nicht in der Zukunft liegt.
     */
    public static function istGueltigesGeburtsdatum(string $geburtsdatum): bool
    {
        $datum = DateTime::createFromFormat('Y-m-d', $geburtsdatum);

        // Vergleich ueber den formatierten Datumsstring statt des DateTime-Objekts direkt:
        // createFromFormat('Y-m-d', ...) uebernimmt fuer die nicht angegebene Uhrzeit die
        // aktuelle Systemzeit, wodurch "heute" faelschlich als "in der Zukunft" durchfiele.
        return $datum !== false && $datum->format('Y-m-d') <= (new DateTime('today'))->format('Y-m-d');
    }

    public static function istGueltigesGeschlecht(string $geschlecht): bool
    {
        return $geschlecht === '' || in_array($geschlecht, self::ERLAUBTE_GESCHLECHTER, true);
    }

    public static function istGueltigeDetails(string $details): bool
    {
        return strlen($details) <= 1000 && !self::enthaeltSqlSchluesselwort($details);
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
