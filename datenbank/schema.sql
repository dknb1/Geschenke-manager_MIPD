-- Anlässe (Geburtstage, Weihnachten, individuelle Anlässe)
-- geschuetzt = 1 kennzeichnet Pflichtanlässe (aktuell: Weihnachten), die nicht gelöscht
-- werden können (siehe Anlass::loeschen()). Wird beim ersten Verbindungsaufbau automatisch
-- befüllt, siehe Datenbank::seedStandardanlaesse().
CREATE TABLE IF NOT EXISTS anlaesse (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    datum TEXT NOT NULL,
    wiederholt_jaehrlich INTEGER NOT NULL DEFAULT 0,
    geschuetzt INTEGER NOT NULL DEFAULT 0,
    erstellt_am TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Personen (Anforderung A-01 "Personen anlegen").
-- geburtsdatum ist Pflichtfeld (NOT NULL) - analog zu Weihnachten als "obligatorischem"
-- Anlass soll auch der Geburtstag einer Person nicht fehlen duerfen. Es ist bewusst ein Feld
-- auf der Person (nicht ein eigener Anlass-Datensatz): fachlich gehoert der Geburtstag zur
-- Person ("Liste von Personen mit Angaben zu Geburtstagen"), das naechste
-- Vorkommen wird analog zu wiederkehrenden Anlaessen live berechnet (siehe
-- Anlass::naechstesVorkommen() ueber Person::geburtstagAlsAnlass()), nicht als zusaetzliche
-- anlaesse-Zeile dupliziert, die bei Namens-/Datumsaenderung oder Loeschung der Person sonst
-- veralten wuerde. Aus demselben Grund gibt es kein gespeichertes "alter" mehr - wird live aus
-- geburtsdatum berechnet (siehe Person::alter()), damit es nicht mit der Zeit veraltet.
CREATE TABLE IF NOT EXISTS personen (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    geburtsdatum TEXT NOT NULL,
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
-- ON DELETE CASCADE: wird eine Person geloescht, sollen ihre Geschenkideen nicht als
-- Datenleichen mit person_id auf eine nicht mehr existierende Person zurueckbleiben.
-- Setzt voraus, dass PRAGMA foreign_keys = ON ist (siehe Datenbank::neueVerbindung()).
-- fuer_geburtstag: eine Idee ist NICHT zwangslaeufig fuer den Geburtstag der Person gedacht
-- (z. B. ein reines Hochzeitsgeschenk oder eine Idee ganz ohne Anlass) - deshalb eigenes,
-- explizit zu setzendes Flag statt einer automatischen Verknuepfung. Kein Fremdschluessel auf
-- anlaesse noetig/moeglich, da Geburtstage bewusst keine eigene Zeile dort haben (s. o.).
-- geschenk_anlass_id/geschenk_fuer_geburtstag/geschenk_datum: die "Idee -> Geschenk"-
-- Umwandlung (Aufgabenstellung: "inkl. Anlass und Datum"). Bewusst kein separates Statusfeld -
-- offen = geschenk_anlass_id IS NULL UND geschenk_fuer_geburtstag = 0, fest = eines von beiden
-- gesetzt, vergangen/geschenkt = fest UND geschenk_datum in der Vergangenheit (siehe
-- Geschenkidee::anlassNamenInklGeburtstag()). geschenk_fuer_geburtstag existiert separat von
-- geschenk_anlass_id (statt eines Fremdschluessels), weil der Geburtstag - genau wie bei
-- fuer_geburtstag oben - keine eigene Zeile in anlaesse hat, aber trotzdem ein gueltiges Ziel
-- fuer eine feste Zuordnung sein muss (der naheliegendste Anlass ueberhaupt). geschenk_datum
-- wird beim Fest-Machen aus Anlass::naechstesVorkommen() eingefroren, nicht live
-- nachberechnet - sonst koennte ein wiederkehrender Anlass (dessen naechstesVorkommen() nie in
-- der Vergangenheit liegt) niemals als "vergangen" erkannt werden. ON DELETE SET NULL statt
-- CASCADE bei geschenk_anlass_id: loescht man den fest zugeordneten Anlass, soll die Idee
-- erhalten bleiben und nur auf "offen" zurueckfallen, nicht mitgeloescht werden (analog zur
-- Idee-bleibt-erhalten-Regel bei geschenkidee_anlaesse unten).
CREATE TABLE IF NOT EXISTS geschenkideen (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    person_id INTEGER NOT NULL REFERENCES personen(id) ON DELETE CASCADE,
    text TEXT,
    link TEXT,
    bild_link TEXT,
    fuer_geburtstag INTEGER NOT NULL DEFAULT 0,
    geschenk_anlass_id INTEGER REFERENCES anlaesse(id) ON DELETE SET NULL,
    geschenk_fuer_geburtstag INTEGER NOT NULL DEFAULT 0,
    geschenk_datum TEXT,
    erstellt_am TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Verknuepfung Anlaesse <-> Personen (N:M, ersetzt das fruehere anlaesse.person_name).
-- N:M statt einer einzelnen person_id-Spalte auf anlaesse, weil ein Anlass zu mehreren
-- Personen gehoeren kann (z. B. ein gemeinsamer Hochzeitstag zweier Personen).
-- ON DELETE CASCADE auf beiden Seiten: Loeschen eines Anlasses oder einer Person soll keine
-- verwaisten Verknuepfungszeilen hinterlassen. Setzt PRAGMA foreign_keys = ON voraus
-- (siehe Datenbank::neueVerbindung()).
CREATE TABLE IF NOT EXISTS anlass_personen (
    anlass_id INTEGER NOT NULL REFERENCES anlaesse(id) ON DELETE CASCADE,
    person_id INTEGER NOT NULL REFERENCES personen(id) ON DELETE CASCADE,
    PRIMARY KEY (anlass_id, person_id)
);

-- Verknuepfung Geschenkideen <-> Anlaesse (N:M, analog zu anlass_personen oben) - eine Idee
-- kann zu mehreren Anlaessen passen (z. B. sowohl als Weihnachts- als auch als
-- Geburtstagsgeschenk geeignet), und ein Anlass hat i. d. R. mehrere Ideen.
-- ON DELETE CASCADE auf beiden Seiten: Loeschen einer Idee oder eines Anlasses entfernt nur
-- die Verknuepfungszeile, nicht die jeweils andere Seite - eine Idee bleibt also erhalten,
-- wenn der verknuepfte Anlass geloescht wird, verliert dabei nur die Anlass-Zuordnung.
-- Setzt PRAGMA foreign_keys = ON voraus (siehe Datenbank::neueVerbindung()).
CREATE TABLE IF NOT EXISTS geschenkidee_anlaesse (
    geschenkidee_id INTEGER NOT NULL REFERENCES geschenkideen(id) ON DELETE CASCADE,
    anlass_id INTEGER NOT NULL REFERENCES anlaesse(id) ON DELETE CASCADE,
    PRIMARY KEY (geschenkidee_id, anlass_id)
);
