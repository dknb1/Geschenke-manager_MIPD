<?php

require_once __DIR__ . '/../config/Env.php';

/**
 * LLM-gestuetzte Geschenkideen-Generierung ueber Groq (kostenloser Free Plan, Modell
 * openai/gpt-oss-120b seit 2026-09-30, vorher openai/gpt-oss-20b - siehe Aenderungsprotokoll
 * 2026-09-13 und 2026-09-30 fuer die Modellwahl).
 * Nimmt eine Liste bereits bekannter Ideen-Texte (siehe
 * Geschenkidee::datengrundlageFuerGenerierung()), optional die Interessen der Person (feste
 * Kategorien, siehe Interesse) sowie Anlass und Budget der konkreten Anfrage und liefert genau
 * drei neue Vorschlaege. Anlass und Budget kommen bewusst aus festen Listen (ANLAESSE/BUDGETS)
 * statt aus den gespeicherten Anlaessen: deren Namen sind Freitext ("Hochzeit von Anna und
 * Tom") und wuerden sonst personenbezogene Angaben an den externen Anbieter uebertragen.
 *
 * promptAufbauen()/antwortValidieren() sind bewusst als reine, netzwerkfreie Funktionen von
 * anfrageSenden() getrennt, damit sie ohne echten API-Aufruf per PHPUnit testbar sind.
 */
class Ideengenerator
{
    /**
     * Oeffentlich, damit der Zustimmungshinweis auf ideen-generieren.php den Modellnamen von
     * hier liest statt ihn zu duplizieren. Das Modell muss in der Groq-Organisation des
     * API-Keys freigeschaltet sein (siehe Betriebsdokumentation).
     */
    public const MODELL = 'openai/gpt-oss-120b';
    private const API_URL = 'https://api.groq.com/openai/v1/chat/completions';
    private const ANZAHL_VORSCHLAEGE = 3;

    /** Anlass-Auswahl auf ideen-generieren.php; "keiner" wird nicht in den Prompt uebernommen. */
    public const ANLAESSE = [
        'keiner' => 'Kein bestimmter Anlass',
        'geburtstag' => 'Geburtstag',
        'weihnachten' => 'Weihnachten',
        'hochzeit' => 'Hochzeit',
        'jubilaeum' => 'Jubiläum',
        'geburt' => 'Geburt/Taufe',
        'einzug' => 'Einzug',
        'abschluss' => 'Abschluss',
        'dankeschoen' => 'Dankeschön',
    ];

    /** Budget-Auswahl auf ideen-generieren.php; "egal" wird nicht in den Prompt uebernommen. */
    public const BUDGETS = [
        'egal' => 'Egal',
        'bis_20' => 'bis 20 €',
        '20_50' => '20 bis 50 €',
        '50_100' => '50 bis 100 €',
        'ueber_100' => 'über 100 €',
    ];

    /**
     * Unterhalb dieser Anzahl bekannter Ideen (und ohne hinterlegte Interessen) gilt die
     * Datengrundlage als duenn - ideen-generieren.php weist dann darauf hin, dass die
     * Vorschlaege mit Interessen deutlich besser werden (siehe datengrundlageIstDuenn()).
     */
    private const MINDESTANZAHL_IDEEN = 3;

    /**
     * Anfrage/Antwort des zuletzt aufgerufenen generiere() - fuer die Transparenz-Anzeige auf
     * ideen-generieren.php ("was ist im Hintergrund passiert"). Absichtlich NIE die
     * HTTP-Header: die Anfrage enthaelt sonst den API-Key im Authorization-Header.
     */
    private static ?string $letzteAnfrage = null;
    private static ?string $letzteAntwort = null;

    /**
     * @param string[] $beispiele Bereits bekannte Ideen-Texte dieser Person
     * @param string[] $interessen Anzeigenamen der Interessen (siehe Interesse::bezeichnungen())
     * @param string $anlass Schluessel aus ANLAESSE (unbekannte Werte werden ignoriert)
     * @param string $budget Schluessel aus BUDGETS (unbekannte Werte werden ignoriert)
     * @return string[]|null Genau drei Vorschlaege, oder null bei jedem Fehler (fehlender
     *                        API-Key, Netzwerkfehler, Rate-Limit, ungueltige Antwort) - der
     *                        Aufrufer zeigt dann eine allgemeine Fehlermeldung statt eines
     *                        Fatal Errors.
     */
    public static function generiere(
        array $beispiele,
        array $interessen = [],
        string $anlass = 'keiner',
        string $budget = 'egal'
    ): ?array
    {
        self::$letzteAnfrage = null;
        self::$letzteAntwort = null;

        $apiKey = Env::get('GROQ_API_KEY');
        if ($apiKey === null || $apiKey === '') {
            return null;
        }

        $inhalt = self::anfrageSenden($apiKey, self::promptAufbauen($beispiele, $interessen, $anlass, $budget));
        if ($inhalt === null) {
            return null;
        }

        return self::antwortValidieren($inhalt);
    }

    /**
     * Der zuletzt an Groq geschickte Request-Body (ohne HTTP-Header, siehe oben), als
     * lesbar formatiertes JSON - oder null, wenn generiere() noch nicht/erfolglos vor dem
     * eigentlichen Versand (z. B. fehlender API-Key) aufgerufen wurde.
     */
    public static function letzteAnfrage(): ?string
    {
        return self::$letzteAnfrage;
    }

    /**
     * Die zuletzt von Groq empfangene Rohantwort, lesbar formatiert falls gueltiges JSON -
     * auch bei einem Fehler gesetzt (z. B. Rate-Limit-Antwort), damit sich Fehler anhand der
     * tatsaechlichen Antwort nachvollziehen lassen.
     */
    public static function letzteAntwort(): ?string
    {
        return self::$letzteAntwort;
    }

    /**
     * Ob die Datengrundlage so duenn ist, dass die Vorschlaege voraussichtlich beliebig
     * ausfallen - dann soll die Seite vor dem Generieren auf die Interessen hinweisen, statt
     * stillschweigend schwache Ergebnisse zu liefern. Mit mindestens einem Interesse gilt die
     * Grundlage auch ohne Ideen als ausreichend.
     */
    public static function datengrundlageIstDuenn(int $anzahlIdeen, int $anzahlInteressen): bool
    {
        return $anzahlInteressen === 0 && $anzahlIdeen < self::MINDESTANZAHL_IDEEN;
    }

    /**
     * Baut den Prompt nur aus den tatsaechlich vorhandenen Angaben auf - leere Abschnitte
     * (keine Ideen, keine Interessen, "kein bestimmter Anlass", Budget "egal") werden ganz
     * weggelassen statt als "keine Angabe" mitgeschickt.
     *
     * @param string[] $beispiele
     * @param string[] $interessen Anzeigenamen
     */
    public static function promptAufbauen(
        array $beispiele,
        array $interessen = [],
        string $anlass = 'keiner',
        string $budget = 'egal'
    ): string {
        $angaben = [];

        if ($interessen !== []) {
            $angaben[] = 'Interessen der Person: ' . implode(', ', $interessen) . '.';
        }

        if ($beispiele !== []) {
            $angaben[] = "Bereits verschenkte oder geplante Geschenke dieser Person:\n"
                . implode("\n", array_map(fn (string $b) => '- ' . $b, $beispiele));
        }

        if ($anlass !== 'keiner' && isset(self::ANLAESSE[$anlass])) {
            $angaben[] = 'Anlass: ' . self::ANLAESSE[$anlass] . '.';
        }

        if ($budget !== 'egal' && isset(self::BUDGETS[$budget])) {
            $angaben[] = 'Budget: ' . self::BUDGETS[$budget] . '.';
        }

        // Bewusst KEINE konkreten Beispiel-Produktnamen im Prompt (z. B. "Kopfhörer") - ein
        // erster Testlauf hat gezeigt, dass das Modell ein im Prompt genanntes Beispiel
        // ("Bluetooth-Kopfhörer") einfach woertlich als eigenen "neuen" Vorschlag zurueckgab,
        // statt selbst etwas zur Liste Passendes zu erfinden. Stattdessen nur erkennbare
        // Platzhalter ("Platzhalter 1" usw.), die nicht wie ein echtes Geschenk aussehen.
        //
        // Die "Regeln" stammen aus einem Vergleich echter Antworten (siehe Aenderungsprotokoll
        // 2026-09-30): ohne sie kombinierte das Modell mehrere Interessen gern zu erfundenen
        // Fantasieprodukten ("Backreise-Set") oder schlug reine Verbrauchsartikel vor
        // ("USB-C-Ladekabel").
        return "Folgendes ist über eine Person bekannt, für die ein Geschenk gesucht wird:\n\n"
            . implode("\n\n", $angaben)
            . "\n\nRegeln: Jeder Vorschlag ist ein real im Handel erhältliches Produkt oder ein "
            . 'konkretes Erlebnis. Kombiniere nicht mehrere Interessen künstlich zu einem erfundenen '
            . 'Produkt - jeder Vorschlag darf zu einem einzelnen Interesse passen. Wähle etwas, das '
            . 'sich als persönliches Geschenk eignet, keine reinen Verbrauchs- oder Zubehörartikel. '
            . 'Halte ein angegebenes Budget ein.'
            . "\n\nSchlage darauf basierend genau drei NEUE, zu diesen Angaben passende Geschenkideen "
            . 'vor, die noch nicht in einer Liste oben stehen. Ein Vorschlag ist ein kurzer, konkreter '
            . 'Produkt- oder Geschenkname auf Deutsch - er kann aus einem einzelnen Wort oder aus '
            . 'mehreren Wörtern bestehen, solange diese Wörter zusammen EINEN Namen ergeben (kein ganzer '
            . 'Satz, keine Erklärung). Antworte AUSSCHLIESSLICH mit einem JSON-Array aus genau drei '
            . 'solchen Namen, im Format ["Platzhalter 1", "Platzhalter 2", "Platzhalter 3"] - ersetze '
            . 'diese drei Platzhalter durch deine eigenen, zu den Angaben oben passenden Vorschläge.';
    }

    private static function anfrageSenden(string $apiKey, string $prompt): ?string
    {
        $nutzlast = [
            'model' => self::MODELL,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => 0.8,
        ];
        self::$letzteAnfrage = json_encode($nutzlast, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $verbindung = curl_init(self::API_URL);
        curl_setopt_array($verbindung, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($nutzlast),
            CURLOPT_TIMEOUT => 15,
            // Eigenes CA-Bundle statt curl.cainfo aus der php.ini zu vertrauen - je nach
            // Hosting-Umgebung (lokal wie spaeter auf All-Inkl) ist unklar/inkonsistent, ob
            // dieser Wert dort gesetzt ist (lokal beobachtet: die eingebaute "cli-server"-SAPI
            // des PHP-Entwicklungsservers liest curl.cainfo aus der php.ini nicht zuverlaessig
            // ein, obwohl die normale CLI-SAPI dasselbe php.ini korrekt liest). Mit der App
            // ausgeliefertes Bundle macht die SSL-Verifizierung unabhaengig davon.
            CURLOPT_CAINFO => __DIR__ . '/../config/cacert.pem',
        ]);

        $antwort = curl_exec($verbindung);
        $httpCode = curl_getinfo($verbindung, CURLINFO_HTTP_CODE);
        $curlFehler = curl_error($verbindung);
        // Kein curl_close() noetig: seit PHP 8.0 ist ein curl-Handle ein normales Objekt
        // (CurlHandle) statt einer manuell zu schliessenden Resource, es wird automatisch per
        // Garbage Collection freigegeben - curl_close() ist seitdem ein wirkungsloser No-Op
        // und wird ab PHP 8.5 als deprecated gemeldet.

        if ($antwort === false) {
            self::$letzteAntwort = 'Netzwerkfehler: ' . $curlFehler;

            return null;
        }

        // Fuer die Anzeige lesbar formatieren, falls gueltiges JSON (Normalfall) - sonst die
        // Rohantwort unveraendert zeigen (z. B. eine HTML-Fehlerseite statt JSON).
        $dekodiert = json_decode($antwort, true);
        self::$letzteAntwort = $dekodiert !== null
            ? json_encode($dekodiert, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            : $antwort;

        if ($httpCode !== 200) {
            return null;
        }

        return $dekodiert['choices'][0]['message']['content'] ?? null;
    }

    /**
     * @return string[]|null
     */
    public static function antwortValidieren(string $inhalt): ?array
    {
        // Manche Modelle umschliessen die JSON-Antwort trotz gegenteiliger Anweisung mit
        // Markdown-Codebloecken (```json ... ```) - vorsichtshalber entfernen.
        $bereinigt = trim((string) preg_replace('/^```(?:json)?|```$/m', '', trim($inhalt)));

        $vorschlaege = json_decode($bereinigt, true);

        if (!is_array($vorschlaege) || count($vorschlaege) < self::ANZAHL_VORSCHLAEGE) {
            return null;
        }

        $vorschlaege = array_slice(array_values($vorschlaege), 0, self::ANZAHL_VORSCHLAEGE);

        foreach ($vorschlaege as $vorschlag) {
            if (!is_string($vorschlag) || trim($vorschlag) === '') {
                return null;
            }
        }

        return array_map(fn (string $v) => self::zeichenNormalisieren(trim($v)), $vorschlaege);
    }

    /**
     * gpt-oss-120b schreibt Bindestriche teils als geschuetzten Bindestrich (U+2011) und
     * Leerzeichen als (schmale) geschuetzte Leerzeichen - optisch gleich, aber andere Zeichen,
     * die z. B. beim spaeteren Suchen oder Vergleichen von Ideen stoeren wuerden. Vor dem
     * Speichern auf normale Zeichen vereinheitlicht. Gedankenstriche (–) bleiben unberuehrt.
     */
    private static function zeichenNormalisieren(string $text): string
    {
        return str_replace(
            ["\u{2010}", "\u{2011}", "\u{00A0}", "\u{202F}"],
            ['-', '-', ' ', ' '],
            $text
        );
    }
}
