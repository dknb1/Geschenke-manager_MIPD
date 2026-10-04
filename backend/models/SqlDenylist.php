<?php

/**
 * Zusaetzlicher Schutz fuer Freitextfelder; die eigentliche Absicherung sind Prepared Statements.
 * ALTER und UNION fehlen absichtlich, das sind normale Woerter ("Alter").
 */
class SqlDenylist
{
    private const SCHLUESSELWOERTER = [
        'SELECT', 'INSERT', 'UPDATE', 'DELETE', 'DROP', 'EXEC',
        'TRUNCATE', 'CREATE TABLE',
    ];

    private const SONDERZEICHEN = ['--', ';', '/*', '*/'];

    /** Nur ganze Woerter, damit z. B. "Dropbox" nicht als DROP erkannt wird. */
    public static function enthaeltSchluesselwort(string $eingabe): bool
    {
        foreach (self::SCHLUESSELWOERTER as $schluesselwort) {
            if (preg_match('/\b' . preg_quote($schluesselwort, '/') . '\b/i', $eingabe) === 1) {
                return true;
            }
        }

        foreach (self::SONDERZEICHEN as $zeichen) {
            if (str_contains($eingabe, $zeichen)) {
                return true;
            }
        }

        return false;
    }
}
