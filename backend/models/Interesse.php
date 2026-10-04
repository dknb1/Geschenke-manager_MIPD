<?php

require_once __DIR__ . '/../config/Datenbank.php';

/** Interessen einer Person aus einer festen Liste, als Grundlage fuer die Ideengenerierung. */
class Interesse
{
    /** Schluessel (Datenbank) => Anzeigename. */
    public const KATEGORIEN = [
        'lesen' => 'Lesen',
        'kochen' => 'Kochen & Backen',
        'sport' => 'Sport & Fitness',
        'outdoor' => 'Outdoor & Wandern',
        'reisen' => 'Reisen',
        'musik' => 'Musik',
        'film' => 'Film & Serien',
        'gaming' => 'Gaming',
        'technik' => 'Technik & Gadgets',
        'basteln' => 'Basteln & DIY',
        'garten' => 'Garten & Pflanzen',
        'kunst' => 'Kunst & Malen',
        'fotografie' => 'Fotografie',
        'mode' => 'Mode & Schmuck',
        'wellness' => 'Wellness & Entspannung',
        'tiere' => 'Tiere',
        'spiele' => 'Brett- & Kartenspiele',
        'kaffee_tee' => 'Kaffee & Tee',
        'genuss' => 'Wein & Genuss',
        'nachhaltigkeit' => 'Nachhaltigkeit',
    ];

    /**
     * Immer in der Reihenfolge der Liste, damit Anzeige und Prompt gleich sortiert sind.
     *
     * @return string[]
     */
    public static function vonPerson(int $personId): array
    {
        $stmt = Datenbank::verbinden()->prepare(
            'SELECT interesse FROM person_interessen WHERE person_id = :person_id'
        );
        $stmt->execute(['person_id' => $personId]);

        return self::nurGueltige($stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Ersetzt die Interessen einer Person. Unbekannte Werte werden verworfen.
     *
     * @param mixed[] $schluessel
     */
    public static function fuerPersonSetzen(int $personId, array $schluessel): void
    {
        Datenbank::ersetzeVerknuepfung(
            Datenbank::verbinden(),
            'person_interessen',
            'person_id',
            $personId,
            'interesse',
            self::nurGueltige($schluessel)
        );
    }

    /**
     * @param mixed[] $schluessel
     * @return string[]
     */
    public static function nurGueltige(array $schluessel): array
    {
        return array_values(array_filter(
            array_keys(self::KATEGORIEN),
            fn (string $gueltig) => in_array($gueltig, $schluessel, true)
        ));
    }

    /**
     * @param string[] $schluessel
     * @return string[]
     */
    public static function bezeichnungen(array $schluessel): array
    {
        return array_map(fn (string $s) => self::KATEGORIEN[$s], self::nurGueltige($schluessel));
    }
}
