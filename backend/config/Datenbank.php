<?php

class Datenbank
{
    private static ?PDO $verbindung = null;

    /** Spalten, die nach der ersten Version dazukamen. Werden in bestehenden Datenbanken nachgetragen. */
    private const NACHTRAEGLICHE_SPALTEN = [
    'geschenkideen' => [
        'fuer_geburtstag' => 'INTEGER NOT NULL DEFAULT 0',
        'geschenk_anlass_id' => 'INTEGER REFERENCES anlaesse(id) ON DELETE SET NULL',
        'geschenk_fuer_geburtstag' => 'INTEGER NOT NULL DEFAULT 0',
        'geschenk_datum' => 'TEXT',
        'besorgt' => 'INTEGER NOT NULL DEFAULT 0',
        'offene_aufgaben' => 'TEXT',
    ],
    'personen' => [
        'share_token' => 'TEXT',
        'ideen_generiert_am' => 'TEXT',
    ],
];

    public static function verbinden(): PDO
    {
        if (self::$verbindung === null) {
            $pfad = __DIR__ . '/../../datenbank/geschenke_manager.sqlite';
            self::$verbindung = self::neueVerbindung($pfad);
        }

        return self::$verbindung;
    }

    /** Fuer Tests: frische Datenbank im Speicher. */
    public static function fuerTests(): PDO
    {
        self::$verbindung = self::neueVerbindung(':memory:');
        return self::$verbindung;
    }

    private static function neueVerbindung(string $pfad): PDO
    {
        $verbindung = new PDO('sqlite:' . $pfad);
        $verbindung->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // Ohne dieses Pragma ignoriert SQLite Fremdschluessel und ON DELETE CASCADE.
        $verbindung->exec('PRAGMA foreign_keys = ON');
        $verbindung->exec(file_get_contents(__DIR__ . '/../../datenbank/schema.sql'));
        self::migriereFehlendeSpalten($verbindung);
        self::seedStandardanlaesse($verbindung);

        return $verbindung;
    }

    /** Traegt fehlende Spalten nach, ohne vorhandene Daten anzufassen. */
    private static function migriereFehlendeSpalten(PDO $verbindung): void
    {
        foreach (self::NACHTRAEGLICHE_SPALTEN as $tabelle => $spalten) {
            $vorhandeneSpalten = array_column($verbindung->query("PRAGMA table_info($tabelle)")->fetchAll(PDO::FETCH_ASSOC), 'name');

            foreach ($spalten as $spalte => $definition) {
                if (!in_array($spalte, $vorhandeneSpalten, true)) {
                    $verbindung->exec("ALTER TABLE $tabelle ADD COLUMN $spalte $definition");
                }
            }
        }
    }

    /**
     * Ersetzt alle Eintraege eines Datensatzes in einer Verknuepfungstabelle. Tabellen- und
     * Spaltennamen kommen nur aus dem eigenen Code, nie aus Nutzereingaben.
     *
     * @param int[]|string[] $fremdeIds
     */
    public static function ersetzeVerknuepfung(
        PDO $pdo,
        string $tabelle,
        string $eigeneSpalte,
        int $eigeneId,
        string $fremdeSpalte,
        array $fremdeIds
    ): void {
        $pdo->prepare("DELETE FROM $tabelle WHERE $eigeneSpalte = :eigene_id")
            ->execute(['eigene_id' => $eigeneId]);

        $stmt = $pdo->prepare(
            "INSERT INTO $tabelle ($eigeneSpalte, $fremdeSpalte) VALUES (:eigene_id, :fremde_id)"
        );
        foreach (array_unique($fremdeIds) as $fremdeId) {
            $stmt->execute(['eigene_id' => $eigeneId, 'fremde_id' => $fremdeId]);
        }
    }

    /** Legt Weihnachten als Pflichtanlass an, falls es noch fehlt. */
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
