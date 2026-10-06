<?php

require_once __DIR__ . '/../config/Datenbank.php';
require_once __DIR__ . '/Anlass.php';
require_once __DIR__ . '/Person.php';
require_once __DIR__ . '/SqlDenylist.php';

class Geschenkidee
{
    public static function alle(): array
    {
        $pdo = Datenbank::verbinden();
        $stmt = $pdo->query(
            'SELECT geschenkideen.*, personen.name AS person_name
             FROM geschenkideen
             JOIN personen ON personen.id = geschenkideen.person_id
             ORDER BY geschenkideen.erstellt_am DESC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function finden(int $id): ?array
    {
        $pdo = Datenbank::verbinden();
        $stmt = $pdo->prepare('SELECT * FROM geschenkideen WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $idee = $stmt->fetch(PDO::FETCH_ASSOC);
        return $idee !== false ? $idee : null;
    }

    public static function vonPerson(int $personId): array
    {
        $pdo = Datenbank::verbinden();
        $stmt = $pdo->prepare(
            'SELECT * FROM geschenkideen WHERE person_id = :person_id ORDER BY erstellt_am DESC'
        );
        $stmt->execute(['person_id' => $personId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param bool $fuerGeburtstag Idee ist (auch) fuer den Geburtstag gedacht - bewusst kein Automatismus.
     */
    public static function erstellen(
        int $personId,
        ?string $text,
        ?string $link,
        ?string $bildLink,
        array $anlassIds = [],
        bool $fuerGeburtstag = false,
        bool $besorgt = false,
        ?string $offeneAufgaben = null,
        bool $bereitsVerschenkt = false
    ): void {
        $pdo = Datenbank::verbinden();
        $stmt = $pdo->prepare(
            'INSERT INTO geschenkideen (person_id, text, link, bild_link, fuer_geburtstag, besorgt, offene_aufgaben, bereits_verschenkt)
             VALUES (:person_id, :text, :link, :bild_link, :fuer_geburtstag, :besorgt, :offene_aufgaben, :bereits_verschenkt)'
        );
        $stmt->execute([
            'person_id' => $personId,
            'text' => $text,
            'link' => $link,
            'bild_link' => $bildLink,
            'fuer_geburtstag' => $fuerGeburtstag ? 1 : 0,
            'besorgt' => $besorgt ? 1 : 0,
            'offene_aufgaben' => $offeneAufgaben,
            'bereits_verschenkt' => $bereitsVerschenkt ? 1 : 0,
        ]);

        self::anlaesseVerknuepfen($pdo, (int) $pdo->lastInsertId(), $anlassIds);
    }

    public static function aktualisieren(
        int $id,
        int $personId,
        ?string $text,
        ?string $link,
        ?string $bildLink,
        array $anlassIds = [],
        bool $fuerGeburtstag = false,
        bool $besorgt = false,
        ?string $offeneAufgaben = null,
        bool $bereitsVerschenkt = false
    ): void {
        $pdo = Datenbank::verbinden();
        $stmt = $pdo->prepare(
            'UPDATE geschenkideen
             SET person_id = :person_id, text = :text, link = :link, bild_link = :bild_link,
                 fuer_geburtstag = :fuer_geburtstag, besorgt = :besorgt, offene_aufgaben = :offene_aufgaben,
                 bereits_verschenkt = :bereits_verschenkt
             WHERE id = :id'
        );
        $stmt->execute([
            'person_id' => $personId,
            'text' => $text,
            'link' => $link,
            'bild_link' => $bildLink,
            'fuer_geburtstag' => $fuerGeburtstag ? 1 : 0,
            'besorgt' => $besorgt ? 1 : 0,
            'offene_aufgaben' => $offeneAufgaben,
            'bereits_verschenkt' => $bereitsVerschenkt ? 1 : 0,
            'id' => $id,
        ]);

        self::anlaesseVerknuepfen($pdo, $id, $anlassIds);
    }

    /**
     * Macht die Idee zum festen Geschenk fuer einen Anlass. Das Datum wird dabei festgehalten,
     * sonst wuerde ein jaehrlicher Anlass nie als vergangen gelten.
     */
    public static function festMachen(int $id, int $anlassId): void
    {
        $anlass = Anlass::finden($anlassId);
        if ($anlass === null) {
            return;
        }

        $pdo = Datenbank::verbinden();
        $stmt = $pdo->prepare(
            'UPDATE geschenkideen
             SET geschenk_anlass_id = :anlass_id, geschenk_fuer_geburtstag = 0,
                 geschenk_datum = :geschenk_datum
             WHERE id = :id'
        );
        $stmt->execute([
            'anlass_id' => $anlassId,
            'geschenk_datum' => Anlass::naechstesVorkommen($anlass)->format('Y-m-d'),
            'id' => $id,
        ]);
    }

    /** Wie festMachen(), nur fuer den Geburtstag (der hat keine eigene Zeile in anlaesse). */
    public static function festMachenFuerGeburtstag(int $id): void
    {
        $idee = self::finden($id);
        if ($idee === null) {
            return;
        }

        $person = Person::finden((int) $idee['person_id']);
        if ($person === null) {
            return;
        }

        $pdo = Datenbank::verbinden();
        $stmt = $pdo->prepare(
            'UPDATE geschenkideen
             SET geschenk_anlass_id = NULL, geschenk_fuer_geburtstag = 1,
                 geschenk_datum = :geschenk_datum
             WHERE id = :id'
        );
        $stmt->execute([
            'geschenk_datum' => Anlass::naechstesVorkommen(Person::geburtstagAlsAnlass($person))->format('Y-m-d'),
            'id' => $id,
        ]);
    }
    /**
     * Traegt ein schon uebergebenes Geschenk nachtraeglich ein (Datum muss in der Vergangenheit
     * liegen, setzt besorgt). false bei unbekanntem Anlass oder ungueltigem Datum.
     */
    public static function vergangenMachen(int $id, int $anlassId, string $datum): bool
    {
        $anlass = Anlass::finden($anlassId);
        $geschenkDatum = DateTimeImmutable::createFromFormat('Y-m-d', $datum);

        if ($anlass === null
            || $geschenkDatum === false
            || $geschenkDatum->format('Y-m-d') !== $datum
            || $geschenkDatum >= new DateTimeImmutable('today')) {
            return false;
        }

        $pdo = Datenbank::verbinden();
        $stmt = $pdo->prepare(
            'UPDATE geschenkideen
             SET geschenk_anlass_id = :anlass_id, geschenk_fuer_geburtstag = 0,
                 geschenk_datum = :geschenk_datum, besorgt = 1
             WHERE id = :id'
        );
        $stmt->execute([
            'anlass_id' => $anlassId,
            'geschenk_datum' => $datum,
            'id' => $id,
        ]);

        return true;
    }

    /** Wie vergangenMachen(), nur fuer den Geburtstag. */
    public static function vergangenMachenFuerGeburtstag(int $id, string $datum): bool
    {
        $idee = self::finden($id);
        $geschenkDatum = DateTimeImmutable::createFromFormat('Y-m-d', $datum);

        if ($idee === null
            || $geschenkDatum === false
            || $geschenkDatum->format('Y-m-d') !== $datum
            || $geschenkDatum >= new DateTimeImmutable('today')) {
            return false;
        }

        $person = Person::finden((int) $idee['person_id']);
        if ($person === null) {
            return false;
        }

        $pdo = Datenbank::verbinden();
        $stmt = $pdo->prepare(
            'UPDATE geschenkideen
             SET geschenk_anlass_id = NULL, geschenk_fuer_geburtstag = 1,
                 geschenk_datum = :geschenk_datum, besorgt = 1
             WHERE id = :id'
        );
        $stmt->execute([
            'geschenk_datum' => $datum,
            'id' => $id,
        ]);

        return true;
    }

    /**
     * Teilt Ideen in "offen", "fest" (Termin kommt noch) und "vergangen".
     *
     * @return array{offen: array[], fest: array[], vergangen: array[]}
     */
    public static function sortiereNachStatus(array $ideen, ?DateTimeImmutable $heute = null): array
    {
        $heute ??= new DateTimeImmutable('today');
        $offen = [];
        $fest = [];
        $vergangen = [];

        foreach ($ideen as $idee) {
            if (!self::istFest($idee)) {
                $offen[] = $idee;
                continue;
            }

            $istVergangen = !empty($idee['geschenk_datum']) && new DateTimeImmutable($idee['geschenk_datum']) < $heute;

            if ($istVergangen) {
                $vergangen[] = $idee;
            } else {
                $fest[] = $idee;
            }
        }

        return ['offen' => $offen, 'fest' => $fest, 'vergangen' => $vergangen];
    }

    /**
     * Ideen-Texte fuer die KI: alle festen und vergangenen Geschenke plus die 5 neuesten offenen
     * Ideen. Nur Text, keine Links oder Bilder.
     *
     * @return string[]
     */
    public static function datengrundlageFuerGenerierung(int $personId): array
    {
        $sortiert = self::sortiereNachStatus(self::vonPerson($personId));

        $offeneNeueste = $sortiert['offen'];
        usort($offeneNeueste, fn (array $a, array $b) => strcmp($b['erstellt_am'], $a['erstellt_am']));
        $offeneNeueste = array_slice($offeneNeueste, 0, 5);

        $alle = array_merge($sortiert['vergangen'], $sortiert['fest'], $offeneNeueste);

        return array_values(array_filter(array_map(
            fn (array $idee) => trim((string) ($idee['text'] ?? '')),
            $alle
        )));
    }

    /** Ideen fuer den naechsten Geburtstag der Person (lose markiert oder fest zugeordnet). */
    public static function bekannteIdeenFuerGeburtstag(int $personId, ?DateTimeImmutable $heute = null): array
    {
        $heute ??= new DateTimeImmutable('today');

        return array_values(array_filter(
            self::vonPerson($personId),
            function (array $idee) use ($heute) {
                if (self::istFest($idee)) {
                    return (int) $idee['geschenk_fuer_geburtstag'] === 1
                        && !empty($idee['geschenk_datum'])
                        && new DateTimeImmutable($idee['geschenk_datum']) >= $heute;
                }

                return (int) $idee['fuer_geburtstag'] === 1;
            }
        ));
    }

    /**
     * Stand pro Person fuer einen Anlass: Anzahl Ideen, davon besorgt, offene Aufgaben.
     *
     * @return array<int, array{person_id: int, person: string, ideen: int, besorgt: int, offene_aufgaben: string[]}>
     */
    public static function statusNachPersonFuerAnlass(int $anlassId, ?DateTimeImmutable $heute = null): array
    {
        $heute ??= new DateTimeImmutable('today');
        $statusNachPerson = [];

        foreach (self::alle() as $idee) {
            $istFest = self::istFest($idee);

            if ($istFest) {
                $istFuerAnlass = (int) ($idee['geschenk_anlass_id'] ?? 0) === $anlassId;
            } else {
                $loseAnlassIds = array_map('intval', array_column(self::anlaesse((int) $idee['id']), 'id'));
                $istFuerAnlass = in_array($anlassId, $loseAnlassIds, true);
            }

            if (!$istFuerAnlass) {
                continue;
            }

            $istVergangen = $istFest
                && !empty($idee['geschenk_datum'])
                && new DateTimeImmutable($idee['geschenk_datum']) < $heute;

            if ($istVergangen) {
                continue;
            }

            $personId = (int) $idee['person_id'];

            if (!isset($statusNachPerson[$personId])) {
                $statusNachPerson[$personId] = [
                    'person_id' => $personId,
                    'person' => $idee['person_name'],
                    'ideen' => 0,
                    'besorgt' => 0,
                    'offene_aufgaben' => [],
                ];
            }

            $statusNachPerson[$personId]['ideen']++;

            if ((int) ($idee['besorgt'] ?? 0) === 1) {
                $statusNachPerson[$personId]['besorgt']++;
            }

            if (!empty($idee['offene_aufgaben'])) {
                $statusNachPerson[$personId]['offene_aufgaben'][] = trim($idee['offene_aufgaben']);
            }
        }

        return array_values($statusNachPerson);
    }

    /**
     * Weihnachtsstatus fuer die Glocke: Namen mit allem besorgt, mit offenen Ideen und ganz ohne Idee.
     *
     * @return array{besorgt: string[], offen: string[], ohne_idee: string[]}
     */
    public static function zusammenfassungFuerPflichtanlass(int $anlassId, ?DateTimeImmutable $heute = null): array
    {
        $zusammenfassung = ['besorgt' => [], 'offen' => [], 'ohne_idee' => []];
        $mitIdee = [];

        foreach (self::statusNachPersonFuerAnlass($anlassId, $heute) as $status) {
            $mitIdee[$status['person_id']] = true;
            $gruppe = $status['besorgt'] === $status['ideen'] ? 'besorgt' : 'offen';
            $zusammenfassung[$gruppe][] = $status['person'];
        }

        foreach (Person::alle() as $person) {
            if (!isset($mitIdee[(int) $person['id']])) {
                $zusammenfassung['ohne_idee'][] = $person['name'];
            }
        }

        foreach ($zusammenfassung as &$namen) {
            sort($namen, SORT_NATURAL | SORT_FLAG_CASE);
        }

        return $zusammenfassung;
    }

    /** Hebt die feste Zuordnung auf. Die vorherigen Anlass-Tags sind dann wieder sichtbar. */
    public static function zurueckAufOffen(int $id): void
    {
        $pdo = Datenbank::verbinden();
        $stmt = $pdo->prepare(
            'UPDATE geschenkideen
             SET geschenk_anlass_id = NULL, geschenk_fuer_geburtstag = 0, geschenk_datum = NULL
             WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
    }

    public static function istFest(array $idee): bool
    {
        return $idee['geschenk_anlass_id'] !== null || (int) $idee['geschenk_fuer_geburtstag'] === 1;
    }

    /** Fest zuordnen geht nur fuer Anlaesse, die noch bevorstehen. */
    public static function istGueltigerZielAnlass(array $anlass, ?DateTimeImmutable $heute = null): bool
    {
        $heute ??= new DateTimeImmutable('today');
        return Anlass::naechstesVorkommen($anlass, $heute) >= $heute;
    }

    public static function loeschen(int $id): bool
    {
        if (self::finden($id) === null) {
            return false;
        }

        $pdo = Datenbank::verbinden();
        $stmt = $pdo->prepare('DELETE FROM geschenkideen WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return true;
    }

    /** Ersetzt alle Anlass-Verknuepfungen der Idee. */
    private static function anlaesseVerknuepfen(PDO $pdo, int $geschenkideeId, array $anlassIds): void
    {
        Datenbank::ersetzeVerknuepfung($pdo, 'geschenkidee_anlaesse', 'geschenkidee_id', $geschenkideeId, 'anlass_id', $anlassIds);
    }

    public static function anlaesse(int $geschenkideeId): array
    {
        $pdo = Datenbank::verbinden();
        $stmt = $pdo->prepare(
            'SELECT anlaesse.*
             FROM geschenkidee_anlaesse
             JOIN anlaesse ON anlaesse.id = geschenkidee_anlaesse.anlass_id
             WHERE geschenkidee_anlaesse.geschenkidee_id = :geschenkidee_id'
        );
        $stmt->execute(['geschenkidee_id' => $geschenkideeId]);
        $anlaesse = $stmt->fetchAll(PDO::FETCH_ASSOC);

        usort(
            $anlaesse,
            fn (array $a, array $b) => Anlass::naechstesVorkommen($a) <=> Anlass::naechstesVorkommen($b)
        );

        return $anlaesse;
    }

    /**
     * Anlassnamen mit Datum fuer die Anzeige. Bei fester Zuordnung nur dieser eine Anlass, sonst
     * die losen Tags (vergangene einmalige Anlaesse ausgeblendet).
     */
    public static function anlassNamenInklGeburtstag(int $geschenkideeId, ?DateTimeImmutable $heute = null): array
    {
        $idee = self::finden($geschenkideeId);
        if ($idee === null) {
            return [];
        }

        $heute ??= new DateTimeImmutable('today');

        if (self::istFest($idee)) {
            $datum = new DateTimeImmutable($idee['geschenk_datum']);
            $istVergangen = $datum < $heute;

            if ((int) $idee['geschenk_fuer_geburtstag'] === 1) {
                $person = Person::finden((int) $idee['person_id']);
                if ($person === null) {
                    return [];
                }
                $name = Person::geburtstagAlsAnlass($person)['name'];
            } else {
                $festerAnlass = Anlass::finden((int) $idee['geschenk_anlass_id']);
                if ($festerAnlass === null) {
                    return [];
                }
                $name = $festerAnlass['name'];
            }

            $namenMitDatum = $name . ' - ' . $datum->format('d.m.Y');

            return [$namenMitDatum . ($istVergangen ? ' (vergangen)' : '')];
        }

        $namen = array_map(
            fn (array $anlass) => $anlass['name'] . ' - ' . Anlass::naechstesVorkommen($anlass, $heute)->format('d.m.Y'),
            array_values(array_filter(
                self::anlaesse($geschenkideeId),
                fn (array $anlass) => Anlass::naechstesVorkommen($anlass, $heute) >= $heute
            ))
        );

        if ((int) $idee['fuer_geburtstag'] === 1) {
            $person = Person::finden((int) $idee['person_id']);
            if ($person !== null) {
                $geburtstagAnlass = Person::geburtstagAlsAnlass($person);
                $geburtstagDatum = Anlass::naechstesVorkommen($geburtstagAnlass, $heute)->format('d.m.Y');
                array_unshift($namen, $geburtstagAnlass['name'] . ' - ' . $geburtstagDatum);
            }
        }

        return $namen;
    }

    /** Text, Link oder Bild - mindestens eins davon muss angegeben sein. */
    public static function hatInhalt(string $text, string $link, string $bildLink): bool
    {
        return $text !== '' || $link !== '' || $bildLink !== '';
    }

    public static function istGueltigerText(string $text): bool
    {
        return $text === '' || (strlen($text) <= 1000 && !SqlDenylist::enthaeltSchluesselwort($text));
    }

    public static function istGueltigeUrl(string $url): bool
    {
        return $url === '' || (strlen($url) <= 2000 && filter_var($url, FILTER_VALIDATE_URL) !== false);
    }

    /** Sammelt die Fehlermeldungen fuer das Ideen-Formular (leer = gueltig). */
    public static function validiereEingabe(?array $person, string $text, string $link, string $bildLink, string $offeneAufgaben = ''): array
    {
        $fehler = [];

        if ($person === null) {
            $fehler[] = 'Bitte eine Person auswählen.';
        }

        if (!self::hatInhalt($text, $link, $bildLink)) {
            $fehler[] = 'Bitte mindestens einen Inhalt angeben: Text, Link oder Bild.';
        }

        if (!self::istGueltigerText($text)) {
            $fehler[] = 'Die Idee enthält nicht erlaubte Inhalte oder ist zu lang (max. 1000 Zeichen).';
        }

        if (!self::istGueltigerText($offeneAufgaben)) {
            $fehler[] = 'Die offenen Aufgaben enthalten nicht erlaubte Inhalte oder sind zu lang (max. 1000 Zeichen).';
        }

        if (!self::istGueltigeUrl($link)) {
            $fehler[] = 'Bitte einen gültigen Link angeben (z. B. https://...).';
        }

        if (!self::istGueltigeUrl($bildLink)) {
            $fehler[] = 'Bitte einen gültigen Bild-Link angeben (z. B. https://...).';
        }

        return $fehler;
    }
}
