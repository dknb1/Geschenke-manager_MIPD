-- Anlässe (Geburtstage, Weihnachten, individuelle Anlässe)
-- person_name ist vorerst freier Text, da die Personen-Datenstruktur (Paket "Personen")
-- noch nicht existiert. Sobald es eine personen-Tabelle gibt, sollte hier auf
-- person_id INTEGER REFERENCES personen(id) umgestellt werden.
-- geschuetzt = 1 kennzeichnet Pflichtanlässe (aktuell: Weihnachten), die nicht gelöscht
-- werden können (siehe Anlass::loeschen()). Wird beim ersten Verbindungsaufbau automatisch
-- befüllt, siehe Datenbank::seedStandardanlaesse().
CREATE TABLE IF NOT EXISTS anlaesse (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    datum TEXT NOT NULL,
    wiederholt_jaehrlich INTEGER NOT NULL DEFAULT 0,
    person_name TEXT,
    geschuetzt INTEGER NOT NULL DEFAULT 0,
    erstellt_am TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Personen (Anforderung A-01 "Personen anlegen").
-- "alter" ist ein SQL-Schluesselwort (siehe ALTER TABLE) und wird deshalb in allen
-- Statements in doppelten Anfuehrungszeichen verwendet, um Parserfehler zu vermeiden.
CREATE TABLE IF NOT EXISTS personen (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    "alter" INTEGER,
    geschlecht TEXT,
    details TEXT,
    erstellt_am TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Geschenkideen (Anforderung A-03 "Ideen speichern").
-- Eine Idee gehoert immer zu genau einer Person und kann als Text, Link
-- und/oder Bild angegeben werden (mindestens eines der drei, siehe
-- Geschenkidee::hatInhalt()). Bilder werden bewusst nicht als Datei/BLOB
-- abgelegt, sondern nur als URL referenziert (bild_link) - kein
-- Datei-Upload/Speicherplatz auf dem Server noetig.
CREATE TABLE IF NOT EXISTS geschenkideen (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    person_id INTEGER NOT NULL REFERENCES personen(id),
    text TEXT,
    link TEXT,
    bild_link TEXT,
    erstellt_am TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE TABLE IF NOT EXISTS einstellungen (
    name TEXT PRIMARY KEY,
    wert TEXT NOT NULL
);

INSERT OR IGNORE INTO einstellungen (name, wert)
VALUES ('benachrichtigung_tage', '30');
