<?php

require_once __DIR__ . '/../config/Env.php';

/**
 * LLM-gestuetzte Geschenkideen-Generierung ueber Groq (kostenloser Free Plan, Modell
 * openai/gpt-oss-20b - siehe Aenderungsprotokoll 2026-09-13 fuer die Modellwahl-Begruendung).
 * Nimmt eine Liste bereits bekannter Ideen-Texte (siehe
 * Geschenkidee::datengrundlageFuerGenerierung()) und liefert genau drei neue Vorschlaege.
 *
 * promptAufbauen()/antwortValidieren() sind bewusst als reine, netzwerkfreie Funktionen von
 * anfrageSenden() getrennt, damit sie ohne echten API-Aufruf per PHPUnit testbar sind.
 */
class Ideengenerator
{
    private const MODELL = 'openai/gpt-oss-20b';
    private const API_URL = 'https://api.groq.com/openai/v1/chat/completions';
    private const ANZAHL_VORSCHLAEGE = 3;

    /**
     * Anfrage/Antwort des zuletzt aufgerufenen generiere() - fuer die Transparenz-Anzeige auf
     * ideen-generieren.php ("was ist im Hintergrund passiert"). Absichtlich NIE die
     * HTTP-Header: die Anfrage enthaelt sonst den API-Key im Authorization-Header.
     */
    private static ?string $letzteAnfrage = null;
    private static ?string $letzteAntwort = null;

    /**
     * @param string[] $beispiele Bereits bekannte Ideen-Texte dieser Person
     * @return string[]|null Genau drei Vorschlaege, oder null bei jedem Fehler (fehlender
     *                        API-Key, Netzwerkfehler, Rate-Limit, ungueltige Antwort) - der
     *                        Aufrufer zeigt dann eine allgemeine Fehlermeldung statt eines
     *                        Fatal Errors.
     */
    public static function generiere(array $beispiele): ?array
    {
        self::$letzteAnfrage = null;
        self::$letzteAntwort = null;

        $apiKey = Env::get('GROQ_API_KEY');
        if ($apiKey === null || $apiKey === '') {
            return null;
        }

        $inhalt = self::anfrageSenden($apiKey, self::promptAufbauen($beispiele));
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
     * @param string[] $beispiele
     */
    public static function promptAufbauen(array $beispiele): string
    {
        $liste = implode("\n", array_map(fn (string $b) => '- ' . $b, $beispiele));

        return "Hier ist eine Liste bereits verschenkter oder geplanter Geschenke einer Person:\n"
            . $liste
            . "\n\nSchlage darauf basierend genau drei NEUE, passende Geschenkideen vor, die noch "
            . "nicht in der Liste stehen. Antworte AUSSCHLIESSLICH mit einem JSON-Array aus genau "
            . 'drei kurzen deutschen Stichwoertern oder Produktnamen, ohne jede weitere Erklaerung, '
            . 'zum Beispiel ["Kopfhörer", "Kochbuch", "Wanderrucksack"].';
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

        return array_map('trim', $vorschlaege);
    }
}
