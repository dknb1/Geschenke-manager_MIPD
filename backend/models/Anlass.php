<?php

require_once __DIR__ . '/../config/Datenbank.php';
require_once __DIR__ . '/Person.php';

class Anlass
{
    /**
     * Sortiert nach dem nächsten Vorkommen (nicht dem rohen Datum), damit wiederkehrende
     * Anlässe nicht dauerhaft an ihrer ursprünglichen Kalenderposition "einfrieren".
     */
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

    /**
     * Alle echten Anlaesse (siehe alle()) zusammen mit den live berechneten Geburtstagen aller
     * Personen (siehe Person::geburtstagAlsAnlass()), gemeinsam nach naechstem Vorkommen
     * sortiert. Zentrale Stelle fuer dieses Zusammenfuehren, das vorher in anlaesse.php und
     * frontend/includes/navbar.php separat dupliziert war.
     */
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

    /**
     * Alle Personen, deren naechster Geburtstag in den naechsten Kalendermonat faellt (z. B.
     * heute im September -> Geburtstage im Oktober, unabhaengig von der einstellbaren
     * "Tage vorher"-Erinnerung) - fuer die monatliche Geburtstags-Vorschau in der
     * Benachrichtigungsglocke (siehe navbar.php). $heute ist fuer Tests injizierbar.
     */
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

    /**
     * Buendelt die Validierung von Name und Datum eines Anlasses fuer erstellen()/
     * aktualisieren() in einer Liste verstaendlicher Fehlermeldungen (leer = gueltig) -
     * zentrale Stelle statt dieselbe Pruefung in anlass-erstellen.php und
     * anlass-bearbeiten.php dupliziert zu pflegen. Die Wiederholung wird bewusst NICHT hier
     * mitgeprueft, da anlass-bearbeiten.php sie bei geschuetzten Anlaessen anders behandelt
     * (siehe dort) - das ist der einzige Teil, der zwischen beiden Seiten wirklich abweicht.
     */
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
     * Liefert für wiederkehrende Anlässe das nächste noch bevorstehende Datum (Monat/Tag
     * bleiben, Jahr wird ggf. hochgezählt); für einmalige Anlässe das gespeicherte Datum
     * unverändert. Hinweis: 29. Februar wird in Nicht-Schaltjahren auf den 1. März
     * verschoben (DateTime-Standardverhalten) — für dieses Prototyp-Projekt akzeptiert.
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

    /**
     * @param int[] $personIds IDs der Personen, die zu diesem Anlass gehoeren (kann leer sein -
     *                         ein Anlass muss nicht zwingend an Personen geknuepft sein)
     */
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
     * @param int[] $personIds Wird für geschuetzte Anlaesse (z. B. Weihnachten) ignoriert -
     *                         die betreffen laut Fachlichkeit alle Personen gleichzeitig und
     *                         duerfen keiner einzelnen Person zugeordnet werden. Aus demselben
     *                         Grund bleibt bei geschuetzten Anlaessen auch $wiederholtJaehrlich
     *                         unveraendert - wuerde man es auf "nein" umstellen, wuerde
     *                         naechstesVorkommen() das Datum stumpf einfrieren statt es
     *                         jaehrlich hochzuzaehlen.
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

    /**
     * Ersetzt die komplette Personen-Verknuepfung eines Anlasses durch $personIds (loeschen +
     * neu anlegen statt Diff, da die Mengen im Prototyp-Umfang klein sind).
     *
     * @param int[] $personIds
     */
    private static function personenVerknuepfen(PDO $pdo, int $anlassId, array $personIds): void
    {
        Datenbank::ersetzeVerknuepfung($pdo, 'anlass_personen', 'anlass_id', $anlassId, 'person_id', $personIds);
    }

    /**
     * Alle Personen, die zu diesem Anlass gehoeren (alphabetisch sortiert).
     */
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

    /**
     * Alle individuellen Anlässe, die mit dieser Person verknüpft sind, sortiert nach
     * nächstem Vorkommen. Enthält NICHT den automatisch berechneten Geburtstag der Person
     * (siehe Person::geburtstagAlsAnlass()) - der ist kein echter anlaesse-Datensatz.
     */
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

    /**
     * Alle geschuetzten Pflichtanlaesse (aktuell: Weihnachten), sortiert nach naechstem
     * Vorkommen. Geschuetzte Anlaesse betreffen fachlich immer alle Personen gleichzeitig
     * (siehe aktualisieren()) und werden deshalb nicht ueber anlass_personen verknuepft -
     * fuer eine "welche Anlaesse betreffen diese Person"-Anzeige muessen sie zusaetzlich zu
     * vonPerson() eingeblendet werden, siehe person-bearbeiten.php.
     */
    public static function geschuetzte(): array
    {
        return array_values(array_filter(
            self::alle(),
            fn (array $anlass) => (int) $anlass['geschuetzt'] === 1
        ));
    }

    /**
     * Liefert false statt zu löschen, wenn der Anlass geschützt ist (z. B. Weihnachten)
     * oder gar nicht existiert.
     */
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
