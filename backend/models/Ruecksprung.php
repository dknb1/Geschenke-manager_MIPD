<?php

/**
 * Ruecksprungziel nach dem Speichern/Loeschen auf einer Bearbeiten-Seite. Die aufrufende Seite
 * haengt sich selbst als ?zurueck=... an den Link (z. B. person-bearbeiten.php -> Idee
 * bearbeiten), die Zielseite leitet nach erfolgreichem Abschluss genau dorthin zurueck statt
 * immer auf dieselbe feste Uebersicht. Ersetzt den frueheren Ansatz ueber window.history.back(),
 * der bei Direktaufrufen ins Leere lief und nach Zwischen-Redirects (z. B. "Fest machen") nur
 * auf einen frueheren Stand derselben Seite zurueckfuehrte.
 *
 * Der Wert kommt aus der Anfrage und landet in einem Location-Header, daher streng nur lokale
 * Seiten der Form "seite.php" oder "seite.php?name=123&..." - keine Schemata, Hosts, Pfade oder
 * Steuerzeichen (sonst Open Redirect bzw. Header-Injection). Einziger nicht-numerischer
 * Parameterwert ist ein verschachteltes "zurueck", das selbst wieder gueltig sein muss (z. B.
 * zurueck auf eine Idee, die man ihrerseits von einer Personenseite aus geoeffnet hat). Alles
 * andere faellt still auf das Standardziel der jeweiligen Seite zurueck.
 */
class Ruecksprung
{
    private const SEITE = '/^[a-z][a-z-]*\.php$/D';
    private const ZEICHEN = '/^[A-Za-z0-9._?=&%-]+$/D';
    private const PARAMETER_NAME = '/^[a-z_]+$/D';
    private const MAX_VERSCHACHTELUNG = 3;

    public static function istGueltig(mixed $ziel, int $tiefe = 0): bool
    {
        if (!is_string($ziel) || $tiefe > self::MAX_VERSCHACHTELUNG
            || preg_match(self::ZEICHEN, $ziel) !== 1) {
            return false;
        }

        $teile = explode('?', $ziel, 2);

        if (preg_match(self::SEITE, $teile[0]) !== 1) {
            return false;
        }

        if (!isset($teile[1])) {
            return true;
        }

        parse_str($teile[1], $parameter);

        if (empty($parameter)) {
            return false;
        }

        foreach ($parameter as $name => $wert) {
            if (preg_match(self::PARAMETER_NAME, (string) $name) !== 1 || !is_string($wert)) {
                return false;
            }

            $gueltigerWert = $name === 'zurueck'
                ? self::istGueltig($wert, $tiefe + 1)
                : ctype_digit($wert);

            if (!$gueltigerWert) {
                return false;
            }
        }

        return true;
    }

    /** Liest ?zurueck= aus der aktuellen Anfrage, sonst $standard. */
    public static function ausAnfrage(string $standard): string
    {
        $ziel = $_GET['zurueck'] ?? null;
        return self::istGueltig($ziel) ? $ziel : $standard;
    }

    /** Haengt $ziel als zurueck-Parameter an $url an (fuer Links und Zwischen-Redirects). */
    public static function anhaengen(string $url, string $ziel): string
    {
        return $url . (str_contains($url, '?') ? '&' : '?') . 'zurueck=' . rawurlencode($ziel);
    }

    /**
     * Die aktuell aufgerufene Seite inkl. Query als relatives Ziel (ohne Verzeichnis, damit es
     * auch in einem Unterordner auf dem Server passt), oder $standard, falls sie kein gueltiges
     * Ziel ergibt. Fuer Formulare, die an eine andere Seite senden und danach hierher
     * zurueckkehren sollen (siehe einstellung-speichern.php).
     */
    public static function aktuelleSeite(string $requestUri, string $standard): string
    {
        $pfad = parse_url($requestUri, PHP_URL_PATH);
        $query = parse_url($requestUri, PHP_URL_QUERY);
        $ziel = basename(is_string($pfad) ? $pfad : '') . (is_string($query) && $query !== '' ? '?' . $query : '');

        return self::istGueltig($ziel) ? $ziel : $standard;
    }
}
