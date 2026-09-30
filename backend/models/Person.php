<?php

require_once __DIR__ . '/../config/Datenbank.php';
require_once __DIR__ . '/SqlDenylist.php';

class Person
{
    private const ERLAUBTE_GESCHLECHTER = ['maennlich', 'weiblich', 'divers'];

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

    /** @return int ID der neu angelegten Person (z. B. um direkt ihre Interessen zu speichern) */
    public static function erstellen(string $name, string $geburtsdatum, ?string $geschlecht, ?string $details): int
    {
        $pdo = Datenbank::verbinden();
        $stmt = $pdo->prepare(
            'INSERT INTO personen (name, geburtsdatum, geschlecht, details)
             VALUES (:name, :geburtsdatum, :geschlecht, :details)'
        );
        $stmt->execute([
            'name' => $name,
            'geburtsdatum' => $geburtsdatum,
            'geschlecht' => $geschlecht,
            'details' => $details,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function aktualisieren(int $id, string $name, string $geburtsdatum, ?string $geschlecht, ?string $details): void
    {
        $pdo = Datenbank::verbinden();
        $stmt = $pdo->prepare(
            'UPDATE personen
             SET name = :name, geburtsdatum = :geburtsdatum, geschlecht = :geschlecht, details = :details
             WHERE id = :id'
        );
        $stmt->execute([
            'name' => $name,
            'geburtsdatum' => $geburtsdatum,
            'geschlecht' => $geschlecht,
            'details' => $details,
            'id' => $id,
        ]);
    }

    /**
     * Live berechnetes Alter aus dem Geburtsdatum, analog zu Anlass::naechstesVorkommen()
     * keine gespeicherte Spalte - ein statisch eingetragenes Alter wuerde sonst mit der Zeit
     * veralten, ohne dass es jemand nachtraegt. $heute ist fuer Tests injizierbar.
     */
    public static function alter(array $person, ?DateTimeImmutable $heute = null): int
    {
        $geburtsdatum = new DateTimeImmutable($person['geburtsdatum']);
        $heute ??= new DateTimeImmutable('today');

        return $heute->diff($geburtsdatum)->y;
    }

    /**
     * Repraesentiert den Geburtstag dieser Person als Anlass-foermiges Array (gleiche
     * 'datum'/'wiederholt_jaehrlich'-Struktur wie eine anlaesse-Zeile), damit er ueber
     * Anlass::naechstesVorkommen() berechnet und in der Anlassliste einsortiert werden kann -
     * ohne als eigene Zeile in der anlaesse-Tabelle dupliziert zu werden (siehe fachliche
     * Dokumentation, Abschnitt "Pflichtanlässe").
     */
    public static function geburtstagAlsAnlass(array $person): array
    {
        return [
            'id' => null,
            'name' => 'Geburtstag ' . $person['name'],
            'datum' => $person['geburtsdatum'],
            'wiederholt_jaehrlich' => 1,
            'geschuetzt' => 1,
            'ist_geburtstag' => true,
            'person_id' => (int) $person['id'],
        ];
    }

    /**
     * Erzeugt (bzw. erneuert) den Zugriffsschluessel fuer den oeffentlichen Share-Link dieser
     * Person (frontend/share.php?token=...). Ein erneuter Aufruf ueberschreibt einen bereits
     * bestehenden Token und macht damit automatisch jeden zuvor verteilten Link ungueltig -
     * es gibt bewusst nur EINEN Link pro Person, keine mehreren, einzeln widerrufbaren Links.
     * 16 zufaellige Bytes (32 Hex-Zeichen) sind der einzige Zugriffsschutz auf diese Seite, da
     * sie oeffentlich ohne Login erreichbar sein muss - deshalb kryptografisch sicherer Zufall
     * (random_bytes()) statt z. B. einer fortlaufenden ID.
     *
     * @return string|null Der neue Token, oder null, wenn die Person nicht existiert.
     */
    public static function shareTokenGenerieren(int $id): ?string
    {
        if (self::finden($id) === null) {
            return null;
        }

        $token = bin2hex(random_bytes(16));

        $pdo = Datenbank::verbinden();
        $stmt = $pdo->prepare('UPDATE personen SET share_token = :token WHERE id = :id');
        $stmt->execute(['token' => $token, 'id' => $id]);

        return $token;
    }

    /**
     * Findet die Person zu einem Share-Token - Gegenstueck zu shareTokenGenerieren(), genutzt
     * von frontend/share.php. Liefert null sowohl bei leerem/unbekanntem Token als auch bei
     * keiner Person (kein Unterschied in der Fehlermeldung noetig/gewollt).
     */
    public static function findenPerShareToken(string $token): ?array
    {
        if ($token === '') {
            return null;
        }

        $pdo = Datenbank::verbinden();
        $stmt = $pdo->prepare('SELECT * FROM personen WHERE share_token = :token');
        $stmt->execute(['token' => $token]);
        $person = $stmt->fetch(PDO::FETCH_ASSOC);

        return $person !== false ? $person : null;
    }

    /**
     * Eigenes, striktes Cooldown pro Person zusaetzlich zum Groq-eigenen Rate-Limit (siehe
     * Ideengenerator) - der gemeinsame API-Key teilt sich das Limit ueber alle Personen, ein
     * versehentliches Mehrfachklicken auf "Ideen generieren" soll das nicht unnoetig
     * ausschoepfen.
     */
    private const IDEEN_GENERIERUNG_COOLDOWN_SEKUNDEN = 60;

    public static function darfIdeenGenerieren(array $person, ?DateTimeImmutable $heute = null): bool
    {
        if (empty($person['ideen_generiert_am'])) {
            return true;
        }

        $heute ??= new DateTimeImmutable('now');
        $letztesMal = new DateTimeImmutable($person['ideen_generiert_am']);

        return $heute->getTimestamp() - $letztesMal->getTimestamp() >= self::IDEEN_GENERIERUNG_COOLDOWN_SEKUNDEN;
    }

    public static function ideenGenerierungVermerken(int $id): void
    {
        $pdo = Datenbank::verbinden();
        $stmt = $pdo->prepare("UPDATE personen SET ideen_generiert_am = datetime('now') WHERE id = :id");
        $stmt->execute(['id' => $id]);
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

    /**
     * Geburtsdatum ist Pflichtfeld (analog zu Weihnachten als "obligatorischer" Anlass,
     * siehe fachliche Dokumentation) - Leerstring wird deshalb hier bewusst NICHT akzeptiert,
     * die Pflichtfeldpruefung erfolgt separat mit eigener Fehlermeldung wie bei istGueltigerName().
     * Gueltiges Y-m-d-Datum, das nicht in der Zukunft liegt.
     */
    public static function istGueltigesGeburtsdatum(string $geburtsdatum): bool
    {
        $datum = DateTime::createFromFormat('Y-m-d', $geburtsdatum);

        // Vergleich ueber den formatierten Datumsstring statt des DateTime-Objekts direkt:
        // createFromFormat('Y-m-d', ...) uebernimmt fuer die nicht angegebene Uhrzeit die
        // aktuelle Systemzeit, wodurch "heute" faelschlich als "in der Zukunft" durchfiele.
        return $datum !== false && $datum->format('Y-m-d') <= (new DateTime('today'))->format('Y-m-d');
    }

    public static function istGueltigesGeschlecht(string $geschlecht): bool
    {
        return $geschlecht === '' || in_array($geschlecht, self::ERLAUBTE_GESCHLECHTER, true);
    }

    public static function istGueltigeDetails(string $details): bool
    {
        return strlen($details) <= 1000 && !SqlDenylist::enthaeltSchluesselwort($details);
    }

    /**
     * Buendelt die Validierung von Name/Geburtsdatum/Geschlecht/Details fuer erstellen()/
     * aktualisieren() in einer Liste verstaendlicher Fehlermeldungen (leer = gueltig) -
     * zentrale Stelle statt dieselbe Pruefungs-/Fehlertext-Kette in person-anlegen.php und
     * person-bearbeiten.php dupliziert zu pflegen.
     */
    public static function validiereEingabe(string $name, string $geburtsdatum, string $geschlecht, string $details): array
    {
        $fehler = [];

        if ($name === '') {
            $fehler[] = 'Bitte einen Namen angeben.';
        } elseif (!self::istGueltigerName($name)) {
            $fehler[] = 'Der Name darf nur Buchstaben, Leerzeichen, Bindestriche und Apostrophe enthalten.';
        }

        if ($geburtsdatum === '') {
            $fehler[] = 'Bitte ein Geburtsdatum angeben.';
        } elseif (!self::istGueltigesGeburtsdatum($geburtsdatum)) {
            $fehler[] = 'Bitte ein gültiges Geburtsdatum angeben (nicht in der Zukunft).';
        }

        if (!self::istGueltigesGeschlecht($geschlecht)) {
            $fehler[] = 'Bitte ein gültiges Geschlecht auswählen.';
        }

        if (!self::istGueltigeDetails($details)) {
            $fehler[] = 'Die Details enthalten nicht erlaubte Inhalte oder sind zu lang (max. 1000 Zeichen).';
        }

        return $fehler;
    }
}
