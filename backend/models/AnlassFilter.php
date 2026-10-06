<?php

require_once __DIR__ . '/Anlass.php';
require_once __DIR__ . '/Person.php';

/**
 * Filter und Monatsgruppen fuer die Anlassliste. Laeuft ueber die URL (GET), damit man nach dem
 * Bearbeiten in dieselbe Ansicht zurueckkommt - deshalb sind alle Werte ausser "suche" Zahlen.
 */
class AnlassFilter
{
    /** Tage ab heute; 0 = alle kommenden. */
    public const ZEITRAEUME = [
        0 => 'Alle Anlässe',
        7 => 'Nächste 7 Tage',
        30 => 'Nächste 30 Tage',
        90 => 'Nächste 90 Tage',
        365 => 'Nächstes Jahr',
    ];

    public const ARTEN = [
        'geburtstage' => 'Geburtstage',
        'eigene' => 'Eigene Anlässe',
        'pflicht' => 'Weihnachten',
    ];

    /** Liest die Filterwerte; Unbekanntes faellt auf den Standard zurueck. Keine Art gewaehlt = alle. */
    public static function ausAnfrage(array $anfrage): array
    {
        $suche = is_string($anfrage['suche'] ?? null) ? trim($anfrage['suche']) : '';
        $suche = preg_replace('/\s+/u', ' ', $suche);
        if (preg_match("/^[\\p{L}\\p{N} '-]{1,100}$/u", $suche) !== 1) {
            $suche = '';
        }

        $personId = filter_var($anfrage['person'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($personId !== false && Person::finden($personId) === null) {
            $personId = false;
        }

        $zeitraum = filter_var($anfrage['zeitraum'] ?? null, FILTER_VALIDATE_INT);
        if ($zeitraum === false || !array_key_exists($zeitraum, self::ZEITRAEUME)) {
            $zeitraum = 0;
        }

        $arten = [];
        if (($anfrage['filter'] ?? null) === '1') {
            foreach (array_keys(self::ARTEN) as $art) {
                if (($anfrage[$art] ?? null) === '1') {
                    $arten[] = $art;
                }
            }
        }

        return [
            'suche' => $suche,
            'person' => $personId !== false ? $personId : null,
            'zeitraum' => $zeitraum,
            'arten' => $arten === [] ? array_keys(self::ARTEN) : $arten,
            'vergangene' => ($anfrage['vergangene'] ?? null) === '1',
        ];
    }

    public static function istAktiv(array $filter): bool
    {
        return $filter !== self::ausAnfrage([]);
    }

    /** Gefilterte Anlaesse inkl. Geburtstage, ergaenzt um personen, naechstes_vorkommen und vergangen. */
    public static function anwenden(array $filter, ?DateTimeImmutable $heute = null): array
    {
        $heute ??= new DateTimeImmutable('today');
        $fristEnde = $filter['zeitraum'] > 0 ? $heute->modify('+' . $filter['zeitraum'] . ' days') : null;
        $personenJeAnlass = Anlass::personenNamenJeAnlass();
        $personenNamen = array_column(Person::alle(), 'name', 'id');
        $personIdsJeAnlass = $filter['person'] !== null
            ? array_column(Anlass::vonPerson($filter['person']), 'id')
            : [];
        $suche = $filter['suche'];

        $ergebnis = [];

        foreach (Anlass::alleInklGeburtstage() as $anlass) {
            $art = self::art($anlass);
            $datum = Anlass::naechstesVorkommen($anlass, $heute);
            $vergangen = $datum < $heute;
            $personen = $anlass['ist_geburtstag']
                ? [$personenNamen[$anlass['person_id']]]
                : ($personenJeAnlass[(int) $anlass['id']] ?? []);

            if (!in_array($art, $filter['arten'], true)
                || ($vergangen && !$filter['vergangene'])
                || ($fristEnde !== null && ($vergangen || $datum > $fristEnde))
                || !self::passtZurPerson($anlass, $art, $filter['person'], $personIdsJeAnlass)
                || !self::passtZurSuche($anlass['name'], $personen, $suche)) {
                continue;
            }

            $ergebnis[] = $anlass + [
                'personen' => $personen,
                'naechstes_vorkommen' => $datum,
                'vergangen' => $vergangen,
            ];
        }

        return $ergebnis;
    }

    /** Gruppiert nach Monat: "Oktober 2026" => Anlaesse. */
    public static function nachMonatGruppiert(array $anlaesse): array
    {
        $gruppen = [];

        foreach ($anlaesse as $anlass) {
            $datum = $anlass['naechstes_vorkommen'];
            $gruppen[Anlass::MONATSNAMEN[(int) $datum->format('n')] . ' ' . $datum->format('Y')][] = $anlass;
        }

        return $gruppen;
    }

    /** Filter als URL (nur Abweichungen vom Standard). Leerzeichen als %20, weil Ruecksprung kein "+" erlaubt. */
    public static function alsUrl(array $filter): string
    {
        if (!self::istAktiv($filter)) {
            return 'anlaesse.php';
        }

        $parameter = ['filter' => '1'];
        if ($filter['suche'] !== '') {
            $parameter['suche'] = $filter['suche'];
        }
        if ($filter['person'] !== null) {
            $parameter['person'] = (string) $filter['person'];
        }
        if ($filter['zeitraum'] !== 0) {
            $parameter['zeitraum'] = (string) $filter['zeitraum'];
        }
        foreach ($filter['arten'] as $art) {
            $parameter[$art] = '1';
        }
        if ($filter['vergangene']) {
            $parameter['vergangene'] = '1';
        }

        return 'anlaesse.php?' . http_build_query($parameter, '', '&', PHP_QUERY_RFC3986);
    }

    /** Geburtstage zuerst pruefen, sie sind intern auch als "geschuetzt" markiert. */
    private static function art(array $anlass): string
    {
        if ($anlass['ist_geburtstag']) {
            return 'geburtstage';
        }

        return (int) $anlass['geschuetzt'] === 1 ? 'pflicht' : 'eigene';
    }

    /** Zur Person gehoeren ihr Geburtstag, ihre Anlaesse und alle Pflichtanlaesse. */
    private static function passtZurPerson(array $anlass, string $art, ?int $personId, array $anlassIdsDerPerson): bool
    {
        return match (true) {
            $personId === null => true,
            $art === 'geburtstage' => $anlass['person_id'] === $personId,
            $art === 'pflicht' => true,
            default => in_array($anlass['id'], $anlassIdsDerPerson),
        };
    }

    private static function passtZurSuche(string $name, array $personen, string $suche): bool
    {
        if ($suche === '') {
            return true;
        }

        // Regex mit /iu statt mb_strtolower(): findet "äpfel" auch in "Äpfel", ohne die Erweiterung mbstring.
        $muster = '/' . preg_quote($suche, '/') . '/iu';

        foreach (array_merge([$name], $personen) as $text) {
            if (preg_match($muster, $text) === 1) {
                return true;
            }
        }

        return false;
    }
}
