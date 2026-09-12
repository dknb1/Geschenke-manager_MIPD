<?php

class Datenbank
{
    private static ?PDO $verbindung = null;

    /**
     * Spalten, die im Laufe des Projekts NACH der ersten Version einer Tabelle ergaenzt
     * wurden. "CREATE TABLE IF NOT EXISTS" (siehe schema.sql) legt eine Tabelle nur an, wenn
     * sie noch nicht existiert - es traegt bei einer bereits bestehenden Tabelle keine neuen
     * Spalten nach. Bisher musste dafuer die komplette lokale Datenbankdatei geloescht werden,
     * was jedes Mal alle echten Daten vernichtet hat. Stattdessen: fehlende Spalten hier
     * eintragen, migriereFehlendeSpalten() unten ergaenzt sie per ALTER TABLE ADD COLUMN nach
     * (bestehende Zeilen bleiben erhalten, neue Spalte wird mit ihrem DEFAULT-Wert befuellt).
     */
    private const NACHTRAEGLICHE_SPALTEN = [
    'geschenkideen' => [
        'fuer_geburtstag' => 'INTEGER NOT NULL DEFAULT 0',
        'geschenk_anlass_id' => 'INTEGER REFERENCES anlaesse(id) ON DELETE SET NULL',
        'geschenk_fuer_geburtstag' => 'INTEGER NOT NULL DEFAULT 0',
        'geschenk_datum' => 'TEXT',
        'besorgt' => 'INTEGER NOT NULL DEFAULT 0',
        'offene_aufgaben' => 'TEXT',
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
        self::migriereFehlendeSpalten($verbindung);
        self::seedStandardanlaesse($verbindung);

        return $verbindung;
    }

    /**
     * Ergaenzt Spalten aus NACHTRAEGLICHE_SPALTEN, falls sie an einer bereits bestehenden
     * Tabelle noch fehlen (z. B. lokale Datenbankdatei von vor der jeweiligen Spalten-
     * Einfuehrung) - ohne bestehende Zeilen/Daten anzutasten. Bei einer frisch von schema.sql
     * angelegten Tabelle sind alle Spalten ohnehin schon vorhanden, hier passiert dann nichts.
     */
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
     * Ersetzt die komplette Verknuepfung eines Datensatzes in einer N:M-Zwischentabelle
     * (loeschen + neu anlegen statt Diff - die Mengen im Prototyp-Umfang sind klein). Gemeinsame
     * Grundlage fuer Anlass::personenVerknuepfen() (anlass_personen) und
     * Geschenkidee::anlaesseVerknuepfen() (geschenkidee_anlaesse), die vorher denselben
     * DELETE+INSERT-Code jeweils eigenstaendig implementiert hatten.
     *
     * $tabelle/$eigeneSpalte/$fremdeSpalte werden direkt in die SQL-Strings eingesetzt (keine
     * Prepared-Statement-Platzhalter fuer Tabellen-/Spaltennamen moeglich) - das ist hier
     * unbedenklich, weil diese Werte ausschliesslich von den beiden Aufrufstellen im eigenen
     * Code kommen, niemals aus Nutzereingaben (gleiches Vertrauensmodell wie bei
     * migriereFehlendeSpalten() oben). $fremdeIds sind normale Werte und werden parametrisiert
     * gebunden.
     *
     * @param int[] $fremdeIds
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
