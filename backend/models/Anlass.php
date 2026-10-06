<?php

require_once __DIR__ . '/../config/Datenbank.php';
require_once __DIR__ . '/Person.php';

class Anlass
{
    /** Fest hinterlegt, weil die intl-Erweiterung nicht ueberall installiert ist. */
    public const MONATSNAMEN = [
        1 => 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni',
        'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember',
    ];

    /** Sortiert nach dem naechsten Termin, nicht nach dem gespeicherten Datum. */
    public static function alle(): array
    {
        $pdo = Datenbank::verbinden();
        $stmt = $pdo->query('SELECT * FROM anlaesse');
        $anlaesse = $stmt->fetchAll(PDO::FETCH_ASSOC);

        usort(
            $anlaesse,
            fn (array $a, array $b) => self::naechstesVorkommen($a) <=> self::naechstesVorkommen($b)
        );

        return $anlaesse;
    }

    /** Alle Anlaesse plus die Geburtstage aller Personen, nach naechstem Termin sortiert. */
    public static function alleInklGeburtstage(): array
    {
        $anlaesse = array_map(
            static fn (array $a): array => $a + ['ist_geburtstag' => false, 'person_id' => null],
            self::alle()
        );
        $geburtstage = array_map(
            static fn (array $p): array => Person::geburtstagAlsAnlass($p),
            Person::alle()
        );
        $alle = array_merge($anlaesse, $geburtstage);

        usort(
            $alle,
            fn (array $a, array $b) => self::naechstesVorkommen($a) <=> self::naechstesVorkommen($b)
        );

        return $alle;
    }

    /** Personen mit Geburtstag im naechsten Kalendermonat. */
    public static function personenMitGeburtstagImNaechstenMonat(?DateTimeImmutable $heute = null): array
    {
        $heute ??= new DateTimeImmutable('today');
        $monatsanfang = $heute->modify('first day of next month')->setTime(0, 0, 0);
        $monatsende = $monatsanfang->modify('last day of this month')->setTime(23, 59, 59);

        return array_values(array_filter(
            Person::alle(),
            function (array $person) use ($heute, $monatsanfang, $monatsende) {
                $geburtstag = self::naechstesVorkommen(Person::geburtstagAlsAnlass($person), $heute);
                return $geburtstag >= $monatsanfang && $geburtstag <= $monatsende;
            }
        ));
    }

    /** Personen, deren Geburtstag innerhalb der eingestellten Erinnerungsfrist liegt. */
    public static function personenMitGeburtstagInFrist(int $tageVorher, ?DateTimeImmutable $heute = null): array
    {
        $heute ??= new DateTimeImmutable('today');
        $fristEnde = $heute->modify('+' . $tageVorher . ' days');

        return self::nachNaechstemGeburtstagSortiert(array_values(array_filter(
            Person::alle(),
            fn (array $person) =>
                self::naechstesVorkommen(Person::geburtstagAlsAnlass($person), $heute) <= $fristEnde
        )), $heute);
    }

    /**
     * Geburtstage im naechsten Monat, die nicht schon in der Frist liegen. Getrennt gehalten,
     * damit die Glocke sie als Vorschau zeigen kann, ohne sie mitzuzaehlen.
     */
    public static function geburtstagsVorschauNaechsterMonat(int $tageVorher, ?DateTimeImmutable $heute = null): array
    {
        $heute ??= new DateTimeImmutable('today');
        $inFrist = array_column(self::personenMitGeburtstagInFrist($tageVorher, $heute), 'id');

        return self::nachNaechstemGeburtstagSortiert(array_values(array_filter(
            self::personenMitGeburtstagImNaechstenMonat($heute),
            fn (array $person) => !in_array($person['id'], $inFrist, true)
        )), $heute);
    }

    private static function nachNaechstemGeburtstagSortiert(array $personen, DateTimeImmutable $heute): array
    {
        usort(
            $personen,
            fn (array $a, array $b) =>
                self::naechstesVorkommen(Person::geburtstagAlsAnlass($a), $heute)
                <=> self::naechstesVorkommen(Person::geburtstagAlsAnlass($b), $heute)
        );

        return $personen;
    }

    /** Prueft Name und Datum. Die Wiederholung prueft die Seite selbst (Pflichtanlaesse weichen ab). */
    public static function validiereNameUndDatum(string $name, string $datum): array
    {
        $fehler = [];

        if ($name === '') {
            $fehler[] = 'Bitte einen Namen für den Anlass angeben.';
        }
        if ($datum === '' || !DateTime::createFromFormat('Y-m-d', $datum)) {
            $fehler[] = 'Bitte ein gültiges Datum angeben.';
        }

        return $fehler;
    }

    /**
     * Naechster Termin: bei jaehrlichen Anlaessen dieses oder naechstes Jahr, sonst das
     * gespeicherte Datum. Der 29. Februar wird in normalen Jahren zum 1. Maerz.
     */
    public static function naechstesVorkommen(array $anlass, ?DateTimeImmutable $heute = null): DateTimeImmutable
    {
        $datum = new DateTimeImmutable($anlass['datum']);

        if ((int) $anlass['wiederholt_jaehrlich'] !== 1) {
            return $datum;
        }

        $heute ??= new DateTimeImmutable('today');
        $kandidat = $datum->setDate((int) $heute->format('Y'), (int) $datum->format('n'), (int) $datum->format('j'));

        if ($kandidat < $heute) {
            $kandidat = $kandidat->modify('+1 year');
        }

        return $kandidat;
    }

    public static function finden(int $id): ?array
    {
        $pdo = Datenbank::verbinden();
        $stmt = $pdo->prepare('SELECT * FROM anlaesse WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $anlass = $stmt->fetch(PDO::FETCH_ASSOC);
        return $anlass !== false ? $anlass : null;
    }

    public static function erstellen(string $name, string $datum, bool $wiederholtJaehrlich, array $personIds = []): void
    {
        $pdo = Datenbank::verbinden();
        $stmt = $pdo->prepare(
            'INSERT INTO anlaesse (name, datum, wiederholt_jaehrlich)
             VALUES (:name, :datum, :wiederholt)'
        );
        $stmt->execute([
            'name' => $name,
            'datum' => $datum,
            'wiederholt' => $wiederholtJaehrlich ? 1 : 0,
        ]);

        self::personenVerknuepfen($pdo, (int) $pdo->lastInsertId(), $personIds);
    }

    /**
     * Bei Pflichtanlaessen werden Personen und Wiederholung ignoriert: sie gelten fuer alle und
     * wiederholen sich immer.
     */
    public static function aktualisieren(int $id, string $name, string $datum, bool $wiederholtJaehrlich, array $personIds = []): void
    {
        $pdo = Datenbank::verbinden();
        $anlass = self::finden($id);

        if ($anlass !== null && (int) $anlass['geschuetzt'] === 1) {
            $personIds = [];
            $wiederholtJaehrlich = (bool) $anlass['wiederholt_jaehrlich'];
        }

        $stmt = $pdo->prepare(
            'UPDATE anlaesse
             SET name = :name, datum = :datum, wiederholt_jaehrlich = :wiederholt
             WHERE id = :id'
        );
        $stmt->execute([
            'name' => $name,
            'datum' => $datum,
            'wiederholt' => $wiederholtJaehrlich ? 1 : 0,
            'id' => $id,
        ]);

        self::personenVerknuepfen($pdo, $id, $personIds);
    }

    private static function personenVerknuepfen(PDO $pdo, int $anlassId, array $personIds): void
    {
        Datenbank::ersetzeVerknuepfung($pdo, 'anlass_personen', 'anlass_id', $anlassId, 'person_id', $personIds);
    }

    public static function personen(int $anlassId): array
    {
        $pdo = Datenbank::verbinden();
        $stmt = $pdo->prepare(
            'SELECT personen.*
             FROM anlass_personen
             JOIN personen ON personen.id = anlass_personen.person_id
             WHERE anlass_personen.anlass_id = :anlass_id
             ORDER BY personen.name COLLATE NOCASE'
        );
        $stmt->execute(['anlass_id' => $anlassId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Personennamen fuer alle Anlaesse in einer Abfrage (anlass_id => Namen). */
    public static function personenNamenJeAnlass(): array
    {
        $pdo = Datenbank::verbinden();
        $stmt = $pdo->query(
            'SELECT anlass_personen.anlass_id, personen.name
             FROM anlass_personen
             JOIN personen ON personen.id = anlass_personen.person_id
             ORDER BY personen.name COLLATE NOCASE'
        );

        $namen = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $zeile) {
            $namen[(int) $zeile['anlass_id']][] = $zeile['name'];
        }

        return $namen;
    }

    /** Anlaesse, die mit der Person verknuepft sind (ohne Geburtstag und Pflichtanlaesse). */
    public static function vonPerson(int $personId): array
    {
        $pdo = Datenbank::verbinden();
        $stmt = $pdo->prepare(
            'SELECT anlaesse.*
             FROM anlass_personen
             JOIN anlaesse ON anlaesse.id = anlass_personen.anlass_id
             WHERE anlass_personen.person_id = :person_id'
        );
        $stmt->execute(['person_id' => $personId]);
        $anlaesse = $stmt->fetchAll(PDO::FETCH_ASSOC);

        usort(
            $anlaesse,
            fn (array $a, array $b) => self::naechstesVorkommen($a) <=> self::naechstesVorkommen($b)
        );

        return $anlaesse;
    }

    /** Anlaesse der Person inklusive Pflichtanlaesse wie Weihnachten: Pflichtanlaesse zuerst, der Rest chronologisch. */
    public static function vonPersonInklGeschuetzte(int $personId): array
    {
        return array_merge(self::geschuetzte(), self::vonPerson($personId));
    }

    /**
     * Auswahl fuer die Ideengenerierung (Schluessel => Name, so wie er an Groq geht). Dient auch
     * als Positivliste fuer das Formular. Beim Geburtstag nur das Wort, ohne Namen der Person.
     */
    public static function auswahlFuerIdeengenerierung(int $personId, ?DateTimeImmutable $heute = null): array
    {
        $heute ??= new DateTimeImmutable('today');
        $auswahl = ['keiner' => 'Kein bestimmter Anlass', 'geburtstag' => 'Geburtstag'];

        foreach (self::vonPersonInklGeschuetzte($personId) as $anlass) {
            if (self::naechstesVorkommen($anlass, $heute) >= $heute) {
                $auswahl[(string) $anlass['id']] = $anlass['name'];
            }
        }

        return $auswahl;
    }

    /** Pflichtanlaesse (aktuell nur Weihnachten). Sie gelten fuer alle Personen. */
    public static function geschuetzte(): array
    {
        return array_values(array_filter(
            self::alle(),
            fn (array $anlass) => (int) $anlass['geschuetzt'] === 1
        ));
    }

    /** false, wenn der Anlass ein Pflichtanlass ist oder nicht existiert. */
    public static function loeschen(int $id): bool
    {
        $anlass = self::finden($id);

        if ($anlass === null || (int) $anlass['geschuetzt'] === 1) {
            return false;
        }

        $pdo = Datenbank::verbinden();
        $stmt = $pdo->prepare('DELETE FROM anlaesse WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return true;
    }
}
