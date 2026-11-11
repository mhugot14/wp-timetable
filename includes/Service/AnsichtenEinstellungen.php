<?php
declare(strict_types=1);

namespace MH\Timetable\Service;

use MH\Timetable\Viewer\TimetableFrontendView;

/**
 * Welche Frontend-Ansichten (Zeitplan, GANTT, Liste, Monat) wählbar sind und
 * welche beim Öffnen erscheint. Global im Backend einstellbar, pro Shortcode
 * überschreibbar: [insertTimetable id="12" ansichten="zeitplan,liste" standard="liste"]
 */
class AnsichtenEinstellungen
{
    public const OPTION = 'mh_tt_ansichten';

    /** @return array{aktiv: string[], standard: string} */
    public static function global(): array
    {
        $gespeichert = get_option(self::OPTION, []);
        return self::normalisieren(
            (array)($gespeichert['aktiv'] ?? array_keys(TimetableFrontendView::ANSICHTEN)),
            (string)($gespeichert['standard'] ?? 'zeitplan')
        );
    }

    /**
     * Globale Einstellung, ggf. überschrieben durch Shortcode-Attribute.
     * @return array{aktiv: string[], standard: string}
     */
    public static function fuerShortcode(string $ansichtenAttr, string $standardAttr): array
    {
        $global = self::global();
        $aktiv = $ansichtenAttr !== ''
            ? array_map('trim', explode(',', strtolower($ansichtenAttr)))
            : $global['aktiv'];
        return self::normalisieren($aktiv, $standardAttr !== '' ? strtolower(trim($standardAttr)) : $global['standard']);
    }

    /** @return string|null Fehlermeldung oder null bei Erfolg */
    public static function speichern(array $aktiv, string $standard): ?string
    {
        $aktiv = array_values(array_intersect(array_keys(TimetableFrontendView::ANSICHTEN), $aktiv));
        if (!$aktiv) return 'Mindestens eine Ansicht muss aktiv sein.';
        if (!in_array($standard, $aktiv, true)) return 'Die Standardansicht muss eine der aktiven Ansichten sein.';
        update_option(self::OPTION, ['aktiv' => $aktiv, 'standard' => $standard], false);
        return null;
    }

    /** @return array{aktiv: string[], standard: string} */
    private static function normalisieren(array $aktiv, string $standard): array
    {
        $alle = array_keys(TimetableFrontendView::ANSICHTEN);
        $aktiv = array_values(array_intersect($alle, $aktiv)) ?: $alle;
        if (!in_array($standard, $aktiv, true)) $standard = $aktiv[0];
        return ['aktiv' => $aktiv, 'standard' => $standard];
    }
}
