<?php

class Datenbank
{
    private static ?PDO $verbindung = null;

    public static function verbinden(): PDO
    {
        if (self::$verbindung === null) {
            $pfad = __DIR__ . '/../../datenbank/geschenke_manager.sqlite';
            self::$verbindung = self::neueVerbindung($pfad);
        }

        return self::$verbindung;
    }

    /**
     * Nur für Tests: setzt die Verbindung auf eine frische In-Memory-Datenbank zurück,
     * damit jeder Testlauf isoliert von der echten Datenbank und von anderen Tests ist.
     */
    public static function fuerTests(): PDO
    {
        self::$verbindung = self::neueVerbindung(':memory:');
        return self::$verbindung;
    }

    private static function neueVerbindung(string $pfad): PDO
    {
        $verbindung = new PDO('sqlite:' . $pfad);
        $verbindung->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // Ohne dieses Pragma ignoriert SQLite REFERENCES/ON DELETE CASCADE stillschweigend
        // (Fremdschluessel werden pro Verbindung, nicht global, aktiviert).
        $verbindung->exec('PRAGMA foreign_keys = ON');
        $verbindung->exec(file_get_contents(__DIR__ . '/../../datenbank/schema.sql'));
        self::seedStandardanlaesse($verbindung);

        return $verbindung;
    }

    /**
     * Weihnachten ist ein fester Pflichtanlass (nicht personenbezogen,
     * anders als Geburtstage). Wird nur einmal angelegt, falls noch nicht vorhanden.
     */
    private static function seedStandardanlaesse(PDO $verbindung): void
    {
        $vorhanden = $verbindung
            ->query("SELECT COUNT(*) FROM anlaesse WHERE geschuetzt = 1 AND name = 'Weihnachten'")
            ->fetchColumn();

        if ((int) $vorhanden === 0) {
            $verbindung->exec(
                "INSERT INTO anlaesse (name, datum, wiederholt_jaehrlich, geschuetzt)
                 VALUES ('Weihnachten', '2026-12-24', 1, 1)"
            );
        }
    }
}
