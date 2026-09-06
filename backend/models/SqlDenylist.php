<?php

/**
 * Zweite Verteidigungslinie fuer Freitextfelder (Person::details, Geschenkidee::text)
 * zusaetzlich zu den im Projekt durchgaengig verwendeten parametrisierten Queries, die die
 * eigentliche Injection-Verhinderung leisten (Anforderung: "Eingabefelder duerfen keine
 * SQL-Schluesselwoerter akzeptieren"). War vorher identisch in Person.php und
 * Geschenkidee.php dupliziert - hier zentral, damit eine Erweiterung der Liste nicht an
 * zwei Stellen synchron gepflegt werden muss.
 *
 * ALTER und UNION bewusst nicht enthalten - beides sind zu gebraeuchliche Alltagswoerter
 * ("Alter" = Lebensalter, "Union" z. B. in Buch-/Filmtiteln), die hier staendig faelschlich
 * abgelehnt wuerden.
 */
class SqlDenylist
{
    private const SCHLUESSELWOERTER = [
        'SELECT', 'INSERT', 'UPDATE', 'DELETE', 'DROP', 'EXEC',
        'TRUNCATE', 'CREATE TABLE',
    ];

    private const SONDERZEICHEN = ['--', ';', '/*', '*/'];

    /**
     * Sucht die Schluesselwoerter als eigenstaendige Woerter (\b-Wortgrenzen), nicht als
     * blosse Teilzeichenkette - sonst wuerden z. B. "Dropbox" oder "Selection" faelschlich
     * abgelehnt, obwohl sie SELECT/DROP nur als Teil eines laengeren Wortes enthalten.
     */
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
