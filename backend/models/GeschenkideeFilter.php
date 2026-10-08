<?php

require_once __DIR__ . '/Geschenkidee.php';
require_once __DIR__ . '/Person.php';

/**
 * Filter fuer die Liste aller Geschenkideen. Laeuft wie der Anlass-Filter ueber die URL (GET),
 * damit man nach dem Bearbeiten in dieselbe Ansicht zurueckkommt - deshalb sind alle Werte
 * ausser "suche" Zahlen.
 */
class GeschenkideeFilter
{
    /** Rubriken wie auf der Personenseite. */
    public const STATUS = [
        'offen' => 'Offene Ideen',
        'fest' => 'Festgelegte Geschenke',
        'vergangen' => 'Vergangene Geschenke',
    ];

    /** Ohne Filter bleiben vergangene Geschenke ausgeblendet, wie vergangene Anlaesse. */
    private const STANDARD_STATUS = ['offen', 'fest'];

    /** Liest die Filterwerte; Unbekanntes faellt auf den Standard zurueck. Kein Status gewaehlt = alle. */
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

        $status = self::STANDARD_STATUS;
        if (($anfrage['filter'] ?? null) === '1') {
            $status = array_values(array_filter(
                array_keys(self::STATUS),
                fn (string $s) => ($anfrage[$s] ?? null) === '1'
            ));
            $status = $status === [] ? array_keys(self::STATUS) : $status;
        }

        $besorgt = match ($anfrage['besorgt'] ?? null) {
            '1' => true,
            '0' => false,
            default => null,
        };

        return [
            'suche' => $suche,
            'person' => $personId !== false ? $personId : null,
            'status' => $status,
            'besorgt' => $besorgt,
        ];
    }

    public static function istAktiv(array $filter): bool
    {
        return $filter !== self::ausAnfrage([]);
    }

    /**
     * Gefilterte Ideen nach Rubrik, nur fuer die gewaehlten Rubriken.
     *
     * @return array<string, array[]> z. B. ['offen' => [...], 'fest' => [...]]
     */
    public static function anwenden(array $filter, ?DateTimeImmutable $heute = null): array
    {
        $sortiert = Geschenkidee::sortiereNachStatus(Geschenkidee::alle(), $heute);
        $ergebnis = [];

        foreach ($filter['status'] as $status) {
            $ergebnis[$status] = array_values(array_filter(
                $sortiert[$status],
                fn (array $idee) => self::passt($idee, $filter)
            ));
        }

        return $ergebnis;
    }

    /** Filter als URL (nur Abweichungen vom Standard). Leerzeichen als %20, weil Ruecksprung kein "+" erlaubt. */
    public static function alsUrl(array $filter): string
    {
        if (!self::istAktiv($filter)) {
            return 'geschenkideen.php';
        }

        $parameter = ['filter' => '1'];
        if ($filter['suche'] !== '') {
            $parameter['suche'] = $filter['suche'];
        }
        if ($filter['person'] !== null) {
            $parameter['person'] = (string) $filter['person'];
        }
        foreach ($filter['status'] as $status) {
            $parameter[$status] = '1';
        }
        if ($filter['besorgt'] !== null) {
            $parameter['besorgt'] = $filter['besorgt'] ? '1' : '0';
        }

        return 'geschenkideen.php?' . http_build_query($parameter, '', '&', PHP_QUERY_RFC3986);
    }

    private static function passt(array $idee, array $filter): bool
    {
        if ($filter['person'] !== null && (int) $idee['person_id'] !== $filter['person']) {
            return false;
        }

        if ($filter['besorgt'] !== null && ((int) ($idee['besorgt'] ?? 0) === 1) !== $filter['besorgt']) {
            return false;
        }

        if ($filter['suche'] === '') {
            return true;
        }

        // Regex mit /iu statt mb_strtolower(): findet "äpfel" auch in "Äpfel", ohne die Erweiterung mbstring.
        $muster = '/' . preg_quote($filter['suche'], '/') . '/iu';

        return preg_match($muster, (string) ($idee['text'] ?? '')) === 1
            || preg_match($muster, $idee['person_name']) === 1;
    }
}
