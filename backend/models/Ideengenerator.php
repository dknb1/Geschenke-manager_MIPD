<?php

require_once __DIR__ . '/../config/Env.php';

/**
 * Geschenkideen per KI (Groq). Geschickt werden nur Ideen-Texte, Interessen, Anlassname, Budget
 * und eine freiwillig gewaehlte Altersgruppe, keine weiteren Personendaten. Prompt-Aufbau und Antwortpruefung sind ohne Netzwerk
 * testbar.
 */
class Ideengenerator
{
    /** Oeffentlich, damit der Zustimmungshinweis den Modellnamen anzeigen kann. */
    public const MODELL = 'openai/gpt-oss-120b';
    private const API_URL = 'https://api.groq.com/openai/v1/chat/completions';
    private const ANZAHL_VORSCHLAEGE = 3;

    /** "egal" landet nicht im Prompt. */
    public const BUDGETS = [
        'egal' => 'Egal',
        'bis_20' => 'bis 20 €',
        '20_50' => '20 bis 50 €',
        '50_100' => '50 bis 100 €',
        'ueber_100' => 'über 100 €',
    ];

    /** Grobe Gruppen statt Alter oder Freitext; "keine" landet nicht im Prompt. */
    public const ALTERSGRUPPEN = [
        'keine' => 'Keine Angabe',
        'kleinkind' => 'Kleinkind (0 bis 3 Jahre)',
        'kind' => 'Kind (4 bis 12 Jahre)',
        'jugendlich' => 'Jugendliche (13 bis 17 Jahre)',
        'erwachsen' => 'Erwachsene (18 bis 64 Jahre)',
        'senior' => 'Senioren (ab 65 Jahre)',
    ];

    /** Weniger Ideen und keine Interessen: Seite weist darauf hin, dass Interessen helfen. */
    private const MINDESTANZAHL_IDEEN = 3;

    /** Fuer die Anzeige "Anfrage/Antwort an Groq". Nie mit HTTP-Headern, dort steht der API-Key. */
    private static ?string $letzteAnfrage = null;
    private static ?string $letzteAntwort = null;

    /**
     * @param string|null $anlass Name des gewaehlten Anlasses, null = keiner
     * @return string[]|null Drei Vorschlaege oder null bei jedem Fehler
     */
    public static function generiere(
        array $beispiele,
        array $interessen = [],
        ?string $anlass = null,
        string $budget = 'egal',
        string $altersgruppe = 'keine'
    ): ?array
    {
        self::$letzteAnfrage = null;
        self::$letzteAntwort = null;

        $apiKey = Env::get('GROQ_API_KEY');
        if ($apiKey === null || $apiKey === '') {
            return null;
        }

        $inhalt = self::anfrageSenden($apiKey, self::promptAufbauen($beispiele, $interessen, $anlass, $budget, $altersgruppe));
        if ($inhalt === null) {
            return null;
        }

        return self::antwortValidieren($inhalt);
    }

    public static function letzteAnfrage(): ?string
    {
        return self::$letzteAnfrage;
    }

    public static function letzteAntwort(): ?string
    {
        return self::$letzteAntwort;
    }

    public static function datengrundlageIstDuenn(int $anzahlIdeen, int $anzahlInteressen): bool
    {
        return $anzahlInteressen === 0 && $anzahlIdeen < self::MINDESTANZAHL_IDEEN;
    }

    /** Baut den Prompt nur aus vorhandenen Angaben; der Anlassname wird einzeilig und gekuerzt. */
    public static function promptAufbauen(
        array $beispiele,
        array $interessen = [],
        ?string $anlass = null,
        string $budget = 'egal',
        string $altersgruppe = 'keine'
    ): string {
        $angaben = [];

        if ($interessen !== []) {
            $angaben[] = 'Interessen der Person: ' . implode(', ', $interessen) . '.';
        }

        if ($beispiele !== []) {
            $angaben[] = "Bereits verschenkte oder geplante Geschenke dieser Person:\n"
                . implode("\n", array_map(fn (string $b) => '- ' . $b, $beispiele));
        }

        // Auf 100 Zeichen kuerzen; Regex statt mb_substr(), damit es ohne die Erweiterung mbstring laeuft.
        $anlass = $anlass !== null ? preg_replace('/^(.{100}).+$/us', '$1', trim(preg_replace('/\s+/u', ' ', $anlass))) : '';
        if ($anlass !== '') {
            $angaben[] = 'Anlass: ' . $anlass . '.';
        }

        if ($budget !== 'egal' && isset(self::BUDGETS[$budget])) {
            $angaben[] = 'Budget: ' . self::BUDGETS[$budget] . '.';
        }

        if ($altersgruppe !== 'keine' && isset(self::ALTERSGRUPPEN[$altersgruppe])) {
            $angaben[] = 'Altersgruppe: ' . self::ALTERSGRUPPEN[$altersgruppe] . '.';
        }

        // Keine echten Beispielprodukte im Prompt: das Modell hat sie sonst einfach uebernommen.
        // Die Regeln verhindern erfundene Kombiprodukte und reine Verbrauchsartikel.
        return "Folgendes ist über eine Person bekannt, für die ein Geschenk gesucht wird:\n\n"
            . implode("\n\n", $angaben)
            . "\n\nRegeln: Jeder Vorschlag ist ein real im Handel erhältliches Produkt oder ein "
            . 'konkretes Erlebnis. Kombiniere nicht mehrere Interessen künstlich zu einem erfundenen '
            . 'Produkt - jeder Vorschlag darf zu einem einzelnen Interesse passen. Wähle etwas, das '
            . 'sich als persönliches Geschenk eignet, keine reinen Verbrauchs- oder Zubehörartikel. '
            . 'Halte ein angegebenes Budget ein. Ist eine Altersgruppe angegeben, muss jeder Vorschlag '
            . 'für dieses Alter geeignet sein.'
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
            // Eigenes CA-Bundle, weil curl.cainfo nicht auf jedem Server (und nicht im PHP-Devserver) greift.
            CURLOPT_CAINFO => __DIR__ . '/../config/cacert.pem',
        ]);

        $antwort = curl_exec($verbindung);
        $httpCode = curl_getinfo($verbindung, CURLINFO_HTTP_CODE);
        $curlFehler = curl_error($verbindung);
        // Kein curl_close() noetig, seit PHP 8 raeumt PHP das selbst auf.

        if ($antwort === false) {
            self::$letzteAntwort = 'Netzwerkfehler: ' . $curlFehler;

            return null;
        }

        // JSON fuer die Anzeige huebsch formatieren, sonst Rohtext zeigen.
        $dekodiert = json_decode($antwort, true);
        self::$letzteAntwort = $dekodiert !== null
            ? json_encode($dekodiert, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            : $antwort;

        if ($httpCode !== 200) {
            return null;
        }

        return $dekodiert['choices'][0]['message']['content'] ?? null;
    }

    public static function antwortValidieren(string $inhalt): ?array
    {
        // Manche Modelle packen das JSON trotzdem in einen Markdown-Codeblock.
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

    /** Das Modell nutzt teils geschuetzte Bindestriche/Leerzeichen; die werden zu normalen Zeichen. */
    private static function zeichenNormalisieren(string $text): string
    {
        return str_replace(
            ["\u{2010}", "\u{2011}", "\u{00A0}", "\u{202F}"],
            ['-', '-', ' ', ' '],
            $text
        );
    }
}
