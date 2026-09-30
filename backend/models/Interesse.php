<?php

require_once __DIR__ . '/../config/Datenbank.php';

/**
 * Interessen einer Person (Tabelle person_interessen) aus einer festen Kategorienliste - als
 * zusaetzliche Grundlage fuer die Ideengenerierung, gerade wenn fuer eine Person noch kaum
 * Geschenkideen existieren. Bewusst nur feste Kategorien statt Freitext: an Groq sollen keine
 * frei formulierten, ggf. personenbezogenen Angaben gehen (aus demselben Grund werden auch
 * Alter, Geschlecht und das Feld "details" nicht uebertragen).
 */
class Interesse
{
    /** Schluessel (in der DB gespeichert) => Anzeigename (in UI und Prompt verwendet). */
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
     * Schluessel der Interessen einer Person, in der Reihenfolge von KATEGORIEN (nicht der
     * Speicherreihenfolge) - so erscheinen sie in UI und Prompt immer gleich sortiert.
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
     * Ersetzt die Interessen einer Person komplett. Unbekannte Schluessel (z. B. manipulierte
     * Formularwerte) werden stillschweigend verworfen, statt in der DB zu landen.
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
     * Filtert auf bekannte Schluessel (ohne Duplikate) und sortiert sie nach KATEGORIEN.
     *
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
     * @return string[] Anzeigenamen in derselben Reihenfolge
     */
    public static function bezeichnungen(array $schluessel): array
    {
        return array_map(fn (string $s) => self::KATEGORIEN[$s], self::nurGueltige($schluessel));
    }
}
