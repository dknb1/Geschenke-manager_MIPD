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

    public static function erstellen(string $name, ?int $alter, ?string $geschlecht, ?string $details): void
    {
        $pdo = Datenbank::verbinden();
        $stmt = $pdo->prepare(
            'INSERT INTO personen (name, "alter", geschlecht, details)
             VALUES (:name, :alter, :geschlecht, :details)'
        );
        $stmt->execute([
            'name' => $name,
            'alter' => $alter,
            'geschlecht' => $geschlecht,
            'details' => $details,
        ]);
    }

    public static function aktualisieren(int $id, string $name, ?int $alter, ?string $geschlecht, ?string $details): void
    {
        $pdo = Datenbank::verbinden();
        $stmt = $pdo->prepare(
            'UPDATE personen
             SET name = :name, "alter" = :alter, geschlecht = :geschlecht, details = :details
             WHERE id = :id'
        );
        $stmt->execute([
            'name' => $name,
            'alter' => $alter,
            'geschlecht' => $geschlecht,
            'details' => $details,
            'id' => $id,
        ]);
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

    public static function istGueltigesAlter(string $alter): bool
    {
        if ($alter === '') {
            return true;
        }

        return (bool) preg_match('/^\d{1,3}$/', $alter) && (int) $alter <= 120;
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
