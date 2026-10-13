<?php

require_once __DIR__ . '/../config/Env.php';

/** Einzelner Login fuer die ganze Anwendung. Der Passwort-Hash steht in der .env. */
class Anmeldung
{
    private const SESSION_SCHLUESSEL = 'angemeldet';

    public static function sessionStarten(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name('geschenke_manager');
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => ($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off',
        ]);
        session_start();
    }

    public static function istAngemeldet(): bool
    {
        return !empty($_SESSION[self::SESSION_SCHLUESSEL]);
    }

    public static function anmelden(string $passwort): bool
    {
        if (!self::passwortKorrekt($passwort, Env::get('LOGIN_PASSWORT_HASH'))) {
            return false;
        }

        // Neue Session-ID, damit eine vorher untergeschobene ID nach dem Login nichts nuetzt.
        session_regenerate_id(true);
        $_SESSION[self::SESSION_SCHLUESSEL] = true;

        return true;
    }

    public static function abmelden(): void
    {
        $cookie = session_get_cookie_params();
        $_SESSION = [];
        session_destroy();
        setcookie(session_name(), '', [
            'expires' => time() - 3600,
            'path' => $cookie['path'],
            'secure' => $cookie['secure'],
            'httponly' => $cookie['httponly'],
            'samesite' => $cookie['samesite'],
        ]);
    }

    /** Ohne hinterlegten Hash ist kein Login moeglich. */
    public static function passwortKorrekt(string $passwort, ?string $hash): bool
    {
        if ($hash === null || $hash === '' || $passwort === '') {
            return false;
        }

        return password_verify($passwort, $hash);
    }
}
