<?php

require_once __DIR__ . '/../config/Datenbank.php';

class Einstellung
{
    public static function benachrichtigungTage(): int
    {
        $pdo = Datenbank::verbinden();

        $stmt = $pdo->query(
            "SELECT wert
             FROM einstellungen
             WHERE name = 'benachrichtigung_tage'"
        );

        $wert = $stmt->fetchColumn();

        return $wert !== false ? (int) $wert : 30;
    }

    public static function benachrichtigungTageSpeichern(int $tage): void
    {
        $pdo = Datenbank::verbinden();

        $stmt = $pdo->prepare(
            "INSERT INTO einstellungen (name, wert)
             VALUES ('benachrichtigung_tage', :wert)
             ON CONFLICT(name)
             DO UPDATE SET wert = :wert"
        );

        $stmt->execute([
            'wert' => $tage
        ]);
    }
}