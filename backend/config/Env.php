<?php

/** Liest die .env im Projektordner (aktuell nur fuer den Groq-API-Key). */
class Env
{
    private static ?array $werte = null;

    public static function get(string $schluessel): ?string
    {
        self::laden();

        return self::$werte[$schluessel] ?? null;
    }

    private static function laden(): void
    {
        if (self::$werte !== null) {
            return;
        }

        self::$werte = [];
        $pfad = __DIR__ . '/../../.env';

        if (!is_file($pfad)) {
            return;
        }

        foreach (file($pfad, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $zeile) {
            $zeile = trim($zeile);

            if ($zeile === '' || str_starts_with($zeile, '#') || !str_contains($zeile, '=')) {
                continue;
            }

            [$name, $wert] = explode('=', $zeile, 2);
            $name = trim($name);
            $wert = trim($wert);

            if (strlen($wert) >= 2 && $wert[0] === $wert[-1] && in_array($wert[0], ['"', "'"], true)) {
                $wert = substr($wert, 1, -1);
            }

            self::$werte[$name] = $wert;
        }
    }
}
