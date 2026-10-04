<?php

/**
 * Wohin nach dem Speichern zurueckgesprungen wird (?zurueck=...). Der Wert landet in einem
 * Location-Header, deshalb sind nur lokale Seiten mit Zahlen als Parametern erlaubt - plus ein
 * verschachteltes "zurueck" und der Suchtext der Anlassliste. Alles andere wird ignoriert.
 */
class Ruecksprung
{
    private const SEITE = '/^[a-z][a-z-]*\.php$/D';
    private const ZEICHEN = '/^[A-Za-z0-9._?=&%-]+$/D';
    private const PARAMETER_NAME = '/^[a-z_]+$/D';
    private const MAX_VERSCHACHTELUNG = 3;
    private const SUCHTEXT = "/^[\\p{L}\\p{N} '-]{1,100}$/uD";

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

            $gueltigerWert = match ($name) {
                'zurueck' => self::istGueltig($wert, $tiefe + 1),
                'suche' => preg_match(self::SUCHTEXT, $wert) === 1,
                default => ctype_digit($wert),
            };

            if (!$gueltigerWert) {
                return false;
            }
        }

        return true;
    }

    public static function ausAnfrage(string $standard): string
    {
        $ziel = $_GET['zurueck'] ?? null;
        return self::istGueltig($ziel) ? $ziel : $standard;
    }

    public static function anhaengen(string $url, string $ziel): string
    {
        return $url . (str_contains($url, '?') ? '&' : '?') . 'zurueck=' . rawurlencode($ziel);
    }

    /** Aktuelle Seite als Ruecksprungziel, fuer Formulare, die an eine andere Seite senden. */
    public static function aktuelleSeite(string $requestUri, string $standard): string
    {
        $pfad = parse_url($requestUri, PHP_URL_PATH);
        $query = parse_url($requestUri, PHP_URL_QUERY);
        $ziel = basename(is_string($pfad) ? $pfad : '') . (is_string($query) && $query !== '' ? '?' . $query : '');

        return self::istGueltig($ziel) ? $ziel : $standard;
    }
}
