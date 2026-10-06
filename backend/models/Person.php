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

    /** @return int ID der neuen Person */
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

    /** Alter wird immer aus dem Geburtsdatum berechnet, nicht gespeichert. */
    public static function alter(array $person, ?DateTimeImmutable $heute = null): int
    {
        $geburtsdatum = new DateTimeImmutable($person['geburtsdatum']);
        $heute ??= new DateTimeImmutable('today');

        return $heute->diff($geburtsdatum)->y;
    }

    /** Alter mit Einheit: "1 Jahr", sonst "Jahre" (auch "0 Jahre"). */
    public static function alterAlsText(array $person, ?DateTimeImmutable $heute = null): string
    {
        $alter = self::alter($person, $heute);

        return $alter . ($alter === 1 ? ' Jahr' : ' Jahre');
    }

    /** Geburtstag in der Form eines Anlasses, damit er wie einer berechnet und angezeigt werden kann. */
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
     * Erzeugt einen neuen Link-Schluessel; der alte Link wird damit ungueltig. Der Schluessel ist
     * der einzige Schutz der oeffentlichen Seite, deshalb echter Zufall.
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

    /** Wartezeit pro Person, damit Mehrfachklicks das gemeinsame Groq-Limit nicht aufbrauchen. */
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

    /** Buchstaben, Leerzeichen, Bindestrich und Apostroph (z. B. "Anna-Lena", "O'Brien"). */
    public static function istGueltigerName(string $name): bool
    {
        return (bool) preg_match('/^[\p{L}\s\'\-]{1,100}$/u', $name);
    }

    /** Gueltiges Datum, nicht in der Zukunft. Leer ist ungueltig (Pflichtfeld). */
    public static function istGueltigesGeburtsdatum(string $geburtsdatum): bool
    {
        $datum = DateTime::createFromFormat('Y-m-d', $geburtsdatum);

        // Als Text vergleichen: createFromFormat() setzt sonst die aktuelle Uhrzeit, und "heute" waere zu spaet.
        return $datum !== false && $datum->format('Y-m-d') <= (new DateTime('today'))->format('Y-m-d');
    }

    /**
     * Gibt es den Namen schon (ohne Gross-/Kleinschreibung)? Namen muessen eindeutig sein, weil
     * Personen fast ueberall nur mit Namen angezeigt werden. In PHP verglichen, weil SQLite bei
     * Umlauten nicht zuverlaessig ohne Gross-/Kleinschreibung vergleicht.
     */
    public static function nameIstVergeben(string $name, ?int $ausserId = null): bool
    {
        // Regex mit /iu statt mb_strtolower(): vergleicht Umlaute ohne die Erweiterung mbstring.
        $gesucht = '/^' . preg_quote(self::normalisierterName($name), '/') . '$/iu';

        foreach (self::alle() as $person) {
            if ((int) $person['id'] !== $ausserId && preg_match($gesucht, self::normalisierterName($person['name'])) === 1) {
                return true;
            }
        }

        return false;
    }

    private static function normalisierterName(string $name): string
    {
        return trim(preg_replace('/\s+/u', ' ', $name));
    }

    public static function istGueltigesGeschlecht(string $geschlecht): bool
    {
        return $geschlecht === '' || in_array($geschlecht, self::ERLAUBTE_GESCHLECHTER, true);
    }

    public static function istGueltigeDetails(string $details): bool
    {
        return strlen($details) <= 1000 && !SqlDenylist::enthaeltSchluesselwort($details);
    }

    /** Sammelt die Fehlermeldungen fuer das Personen-Formular. $ausserId = die gerade bearbeitete Person. */
    public static function validiereEingabe(string $name, string $geburtsdatum, string $geschlecht, string $details, ?int $ausserId = null): array
    {
        $fehler = [];

        if ($name === '') {
            $fehler[] = 'Bitte einen Namen angeben.';
        } elseif (!self::istGueltigerName($name)) {
            $fehler[] = 'Der Name darf nur Buchstaben, Leerzeichen, Bindestriche und Apostrophe enthalten.';
        } elseif (self::nameIstVergeben($name, $ausserId)) {
            $fehler[] = 'Es gibt bereits eine Person mit diesem Namen. Bitte einen unterscheidbaren Namen wählen, z. B. mit Zusatz "Arbeit" oder "Uni".';
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
