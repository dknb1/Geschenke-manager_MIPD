<?php

require_once __DIR__ . '/../config/Datenbank.php';

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

    public static function erstellen(string $name, string $datum, bool $wiederholtJaehrlich, ?string $personName): void
    {
        $pdo = Datenbank::verbinden();
        $stmt = $pdo->prepare(
            'INSERT INTO anlaesse (name, datum, wiederholt_jaehrlich, person_name)
             VALUES (:name, :datum, :wiederholt, :person)'
        );
        $stmt->execute([
            'name' => $name,
            'datum' => $datum,
            'wiederholt' => $wiederholtJaehrlich ? 1 : 0,
            'person' => $personName,
        ]);
    }

    public static function aktualisieren(int $id, string $name, string $datum, bool $wiederholtJaehrlich, ?string $personName): void
    {
        $pdo = Datenbank::verbinden();
        $stmt = $pdo->prepare(
            'UPDATE anlaesse
             SET name = :name, datum = :datum, wiederholt_jaehrlich = :wiederholt, person_name = :person
             WHERE id = :id'
        );
        $stmt->execute([
            'name' => $name,
            'datum' => $datum,
            'wiederholt' => $wiederholtJaehrlich ? 1 : 0,
            'person' => $personName,
            'id' => $id,
        ]);
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
