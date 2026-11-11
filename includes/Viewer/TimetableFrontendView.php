<?php
declare(strict_types=1);

namespace MH\Timetable\Viewer;

use MH\Timetable\Install\Schema;
use MH\Timetable\Model\Entity\Termin;
use MH\Timetable\Model\Entity\Timetable;
use MH\Timetable\Service\IcalService;
use DateTime;

/**
 * Frontend-Darstellung einer Timetable: GANTT, Liste, Monat.
 *
 * Für Personen mit Bearbeitungsrecht (Admin, AL, BGA) kommt ein Bearbeiten-
 * Modus dazu: Balken anklicken öffnet einen Dialog, „+“ legt Termine an.
 * Gespeichert wird über die REST-Schnittstelle, die die Rechte erneut prüft –
 * die Markierungen hier steuern nur die Oberfläche.
 */
class TimetableFrontendView {
    private const MONATE = [1 => 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];

    /** Verfügbare Ansichten in der Reihenfolge der Umschalt-Knöpfe: Schlüssel → [Beschriftung, Dashicon] */
    public const ANSICHTEN = [
        'zeitplan' => ['Zeitplan', 'dashicons-schedule'],
        'gantt'    => ['GANTT', 'dashicons-chart-bar'],
        'liste'    => ['Liste', 'dashicons-list-view'],
        'monat'    => ['Monat', 'dashicons-calendar-alt'],
    ];

    private Timetable $timetable;
    private string $entwurf;
    private array $ferien;
    /** @var array<string,string> Ereignistyp-Kürzel → Farbe */
    private array $farben;
    /**
     * Bearbeiten-Konfiguration; leer = nur lesen.
     * @var array{bildungsgaenge?: string[], ereignistypen?: array<string,string>}
     */
    private array $editor;
    /** @var array<string,string> Ereignistyp-Kürzel → Bezeichnung (Legende) */
    private array $bezeichnungen;
    /** @var string[] aktivierte Ansichten (Schlüssel aus ANSICHTEN) */
    private array $ansichten;
    private string $standardAnsicht;
    /** @var array<string,string> Bildungsgang-Kürzel → Bezeichnung */
    private array $bgNamen;
    /** @var array<int,array{name:string,start:string,ende:string,feiertag:bool}> */
    private array $ferienListe;

    /**
     * @param array{ansichten?: string[], standard?: string, bg_bezeichnungen?: array<string,string>} $optionen
     */
    public function __construct(Timetable $timetable, string $entwurf = 'nein', array $ferien = [], array $farben = [], array $editor = [], array $bezeichnungen = [], array $optionen = []) {
        $this->timetable = $timetable;
        $this->entwurf = $entwurf;
        $this->ferien = $ferien;
        $this->farben = $farben;
        $this->editor = $editor;
        $this->bezeichnungen = $bezeichnungen;
        $this->bgNamen = $optionen['bg_bezeichnungen'] ?? [];
        $this->ferienListe = $optionen['ferien_liste'] ?? [];
        $aktiv = array_values(array_intersect(array_keys(self::ANSICHTEN), $optionen['ansichten'] ?? array_keys(self::ANSICHTEN)));
        $this->ansichten = $aktiv ?: array_keys(self::ANSICHTEN);
        $standard = $optionen['standard'] ?? $this->ansichten[0];
        $this->standardAnsicht = in_array($standard, $this->ansichten, true) ? $standard : $this->ansichten[0];
    }

    private function istEditor(): bool {
        return !empty($this->editor['bildungsgaenge']);
    }

    private function darfBearbeiten(string $bildungsgang): bool {
        return $this->istEditor() && in_array($bildungsgang, $this->editor['bildungsgaenge'], true);
    }

    public function render(): string {
        $uid = (int)$this->timetable->getId();
        $termine = $this->timetable->getTermine();

        if (empty($termine) && !$this->istEditor()) {
            return '<div class="mh-tt-frontend-wrapper"><p>Keine Termine für diese Zeittafel vorhanden.</p></div>';
        }

        $html = sprintf('<div class="mh-tt-frontend-wrapper" id="tt-wrapper-%d" data-timetable-id="%d">', $uid, $uid);

        // 1. Bedienzeile: Filter, Ansichten, ggf. Bearbeiten
        $html .= '<div class="tt-controls-row">';
        $html .= '<div class="tt-filter-box"><strong>Filter:</strong> <div class="tt-multiselect-container">';
        $html .= '<label class="tt-select-all-label"><input type="checkbox" class="tt-select-all" checked> <em>Alle auswählen</em></label>';
        foreach ($this->getUniqueBildungsgaenge() as $bg) {
            $html .= sprintf('<label><input type="checkbox" class="tt-bg-checkbox" value="%s" checked> %s</label>', esc_attr(trim($bg)), esc_html($bg));
        }
        $html .= '</div></div>';

        $html .= '<div class="tt-view-switcher">';
        // Umschalt-Knöpfe nur, wenn es mehr als eine Ansicht gibt
        if (count($this->ansichten) > 1) {
            foreach ($this->ansichten as $key) {
                [$label, $icon] = self::ANSICHTEN[$key];
                $html .= sprintf(
                    '<button type="button" class="tt-switch-btn%s" data-view="tt-view-%s-%d" aria-pressed="%s"><span class="dashicons %s"></span> %s</button>',
                    $key === $this->standardAnsicht ? ' active' : '', $key, $uid, $key === $this->standardAnsicht ? 'true' : 'false', $icon, esc_html($label)
                );
            }
        }
        if ($this->istEditor()) {
            $html .= '<button type="button" class="tt-edit-toggle" aria-pressed="false"><span class="dashicons dashicons-edit"></span> Bearbeiten</button>';
        }
        $html .= '</div></div>';

        // 2. Kopf
        $istEntwurf = $this->entwurf === 'ja';
        $html .= '<h3 class="tt-titel">' . esc_html($this->timetable->getBezeichnung())
            . ($istEntwurf ? ' <span class="tt-entwurf-badge"><span class="dashicons dashicons-edit-page" aria-hidden="true"></span>Entwurf</span>' : '')
            . '</h3>';
        $html .= '<p>' . esc_html($this->timetable->getBeschreibung()) . '</p>';
        if ($istEntwurf) {
            $html .= '<div class="tt-entwurf-hinweis" role="note"><span class="dashicons dashicons-info-outline" aria-hidden="true"></span>'
                . '<span><strong>Noch nicht verbindlich.</strong> Dieser Terminplan ist ein Entwurf – Termine können sich noch ändern. '
                . 'Kalender-Abo und iCal-Download stehen nach der Veröffentlichung zur Verfügung.</span></div>';
        }
        if ($this->entwurf !== 'ja' && !empty($termine)) {
            $html .= sprintf(
                '<p class="tt-abo-hinweis"><span class="dashicons dashicons-calendar-alt"></span> '
                . '<a href="%s">Alle Termine im Kalender abonnieren</a> · <a href="%s">als .ics herunterladen</a>'
                . ' <small>(Abo: Outlook, Apple- und Google-Kalender übernehmen Änderungen automatisch. Einzelne Bildungsgänge: Links neben dem Bildungsgang.)</small></p>',
                esc_url(IcalService::buildUrl($uid, '', true)),
                esc_url(IcalService::buildUrl($uid))
            );
        }

        if ($this->istEditor()) {
            $html .= '<div class="tt-edit-bar">'
                . '<span><span class="dashicons dashicons-info-outline"></span> Bearbeiten-Modus: Termin anklicken zum Ändern, im Zeitplan/GANTT seitlich ziehen zum Verschieben, „+“ legt einen Termin an.</span>'
                . '<button type="button" class="tt-add-btn button" data-bg="">+ Neuer Termin</button>'
                . '</div>';
        }

        $html .= $this->renderLegende();

        // 3. Ansichten
        if (!empty($termine)) {
            foreach ($this->ansichten as $key) {
                $html .= sprintf(
                    '<div id="tt-view-%s-%d" class="tt-view-container tt-view-%s"%s>%s</div>',
                    $key, $uid, $key,
                    $key === $this->standardAnsicht ? '' : ' style="display:none;"',
                    $this->renderAnsicht($key)
                );
            }
        } else {
            $html .= '<p class="tt-leer">Noch keine Termine – im Bearbeiten-Modus über „+ Neuer Termin“ anlegen.</p>';
        }

        if ($this->istEditor()) {
            $html .= $this->renderDialog();
        }

        $html .= '</div>'; // Ende wrapper
        return $html;
    }

    private function renderAnsicht(string $key): string {
        $bgs = $this->editor['bildungsgaenge'] ?? [];
        switch ($key) {
            case 'zeitplan':
                return (new ZeitplanFrontendView($this->timetable, $this->ferien, $this->farben, $this->bezeichnungen, $this->bgNamen, $bgs, $this->entwurf === 'ja', $this->ferienListe))->render();
            case 'gantt':
                return $this->generiereGantt();
            case 'liste':
                return (new ListFrontendView($this->timetable, $this->ferien, $this->farben, $bgs))->render();
            case 'monat':
                return (new MonthFrontendView($this->timetable, $this->ferien, $this->farben, $bgs))->render();
        }
        return '';
    }

    /** Legende der in dieser Timetable verwendeten Ereignistypen. */
    private function renderLegende(): string {
        $typen = array_unique(array_map(fn(Termin $t) => $t->getEreignistyp(), $this->timetable->getTermine()));
        if (!$typen) return '';
        sort($typen, SORT_NATURAL | SORT_FLAG_CASE);
        $html = '<div class="tt-legende" aria-label="Legende der Ereignistypen"><span class="tt-legende-titel">Legende:</span>';
        foreach ($typen as $typ) {
            $erklaerung = trim($this->bezeichnungen[$typ] ?? '');
            $html .= sprintf(
                '<span class="tt-legende-eintrag"><span class="tt-legende-farbe" style="background:%s"></span><strong>%s</strong>%s</span>',
                esc_attr($this->farben[$typ] ?? '#cccccc'),
                esc_html($typ),
                // Erklärung nur zeigen, wenn sie mehr sagt als das Kürzel
                ($erklaerung !== '' && mb_strtolower($erklaerung) !== mb_strtolower($typ)) ? ' = ' . esc_html($erklaerung) : ''
            );
        }
        return $html . '</div>';
    }

    private function generiereGantt(): string {
        $today = (new DateTime())->format('d.m.y');
        $dates = $this->timetable->getDateRange();
        $html = '<div class="timetable-container"><table class="timetablegrid">';
        // Spaltenbreiten fest vorgeben: Bei table-layout:fixed nimmt der Browser sonst
        // die erste Zeile – das ist jetzt die Monatszeile mit zusammengefassten Zellen.
        $html .= '<colgroup><col class="tt-col-bg">' . str_repeat('<col class="tt-col-tag">', count($dates)) . '</colgroup>';
        $html .= $this->renderHeader($dates, $today);
        $html .= $this->renderBody($dates, $today);
        $html .= '</table></div>';
        return $html;
    }

    /** Kopf mit Monatszeile und Tageszeile. */
    private function renderHeader(array $dates, string $today): string {
        // Monatszeile: Spannweite je Monat
        $monate = [];
        foreach ($dates as $date) {
            $key = $date->format('Y-n');
            $monate[$key] = ($monate[$key] ?? 0) + 1;
        }
        $html = '<thead class="timetablegrid_thead"><tr class="tt-month-row"><th class="sticky_column" rowspan="2">Bildungsgang</th>';
        foreach ($monate as $key => $anzahl) {
            [$jahr, $monat] = explode('-', $key);
            // Bei sehr kurzen Abschnitten nur das Kürzel, damit es in die Breite passt
            $label = $anzahl >= 5 ? self::MONATE[(int)$monat] . ' ' . $jahr : mb_substr(self::MONATE[(int)$monat], 0, 3) . '.';
            $html .= sprintf('<th class="tt-month-head" colspan="%d">%s</th>', $anzahl, esc_html($label));
        }
        $html .= '</tr><tr>';

        foreach ($dates as $date) {
            $dateStr = $date->format('d.m.y');
            $css = "timetable_date";
            if (in_array($date->format('N'), ['6', '7'])) $css .= '_weekend';
            if (isset($this->ferien[$dateStr])) $css .= '_holiday';
            if ($dateStr === $today) $css .= ' timetable_date_today';
            if ($date->format('j') === '1') $css .= ' tt-monatsanfang';

            $titel = isset($this->ferien[$dateStr]) ? ' title="' . esc_attr($this->ferien[$dateStr]) . '"' : '';
            $html .= '<th class="' . $css . '"' . $titel . '><div class="vertical-header-wrapper">';
            $html .= '<span class="date-part">' . $date->format('d.m.') . '</span>';
            $html .= '<span class="day-part">' . $this->getWochentag($date) . '</span>';
            $html .= '</div></th>';
        }
        return $html . '</tr></thead>';
    }

    private function renderBody(array $dates, string $today): string {
        $html = '<tbody>';
        $grouped = [];
        foreach ($this->timetable->getTermine() as $t) {
            $grouped[$t->getBildungsgang()][] = $t;
        }

        $rangeStart = $dates[0] ?? null;
        $dayCount = count($dates);
        $ttId = (int)$this->timetable->getId();

        foreach ($grouped as $bg => $bgTermine) {
            $bg = (string)$bg;
            $editierbar = $this->darfBearbeiten($bg);

            // iCal-Links (nur wenn kein Entwurf)
            $icalLink = '';
            if ($this->entwurf !== 'ja') {
                $icalLink = sprintf(
                    ' <a href="%s" class="ical-small-link" title="Termine von %s als .ics herunterladen">iCal</a>'
                    . '<span class="ical-small-link">·</span>'
                    . '<a href="%s" class="ical-small-link" title="Termine von %s im Kalender abonnieren (aktualisiert sich automatisch)">Abo</a>',
                    esc_url(IcalService::buildUrl($ttId, $bg)),
                    esc_attr($bg),
                    esc_url(IcalService::buildUrl($ttId, $bg, true)),
                    esc_attr($bg)
                );
            }
            $plus = $editierbar
                ? sprintf(' <button type="button" class="tt-add-btn tt-add-inline" data-bg="%s" title="Termin für %s anlegen">+</button>', esc_attr($bg), esc_attr($bg))
                : '';

            // Überlappende Termine auf mehrere Spuren (Zeilen) verteilen
            $lanes = $this->verteileAufSpuren($bgTermine);
            $laneCount = count($lanes);

            foreach ($lanes as $laneIdx => $laneTermine) {
                $rowClass = $laneIdx > 0 ? ' class="tt-lane-continued"' : '';
                $html .= '<tr data-bg="' . esc_attr(trim($bg)) . '"' . $rowClass . '>';
                if ($laneIdx === 0) {
                    $rowspan = $laneCount > 1 ? ' rowspan="' . $laneCount . '"' : '';
                    $html .= '<td class="sticky_column"' . $rowspan . '><span class="bg-name">' . esc_html($bg) . '</span>' . $plus . $icalLink . '</td>';
                }

                $idx = 0;
                foreach ($laneTermine as $termin) {
                    $startIdx = $rangeStart ? (int)$rangeStart->diff($termin->getBeginn())->days : 0;

                    while ($idx < $startIdx && $idx < $dayCount) {
                        $html .= $this->renderEmptyCell($dates[$idx], $today);
                        $idx++;
                    }

                    // Termin-Balken (nie über das Tabellenende hinaus)
                    if ($idx < $dayCount) {
                        $dauer = max(1, min($termin->getDauer(), $dayCount - $idx));
                        $label = esc_html($termin->getEreignistyp());
                        if ($dauer > 4) {
                            $label .= ' (' . esc_html($termin->getBezeichnung()) . ')';
                        }
                        $title = sprintf('%s: %s (%s – %s)%s',
                            $termin->getEreignistyp(),
                            $termin->getBezeichnung(),
                            $termin->getBeginn()->format('d.m.'),
                            $termin->getEnde()->format('d.m.'),
                            $termin->getVerantwortlich() !== '' ? ' · ' . $termin->getVerantwortlich() : ''
                        );

                        $html .= sprintf(
                            '<td colspan="%d" class="td_%s tt-bar%s" title="%s" data-termin-id="%d"%s>%s</td>',
                            $dauer,
                            esc_attr(sanitize_title($termin->getEreignistyp())),
                            $editierbar ? ' tt-editierbar' : '',
                            esc_attr($title),
                            (int)$termin->getId(),
                            // Daten für Drag & Drop (nur bei bearbeitbaren Balken)
                            $editierbar ? sprintf(' tabindex="0" role="button" data-beginn="%s" data-ende="%s"',
                                $termin->getBeginn()->format('Y-m-d'), $termin->getEnde()->format('Y-m-d')) : '',
                            $label
                        );
                        $idx += $dauer;
                    }
                }

                while ($idx < $dayCount) {
                    $html .= $this->renderEmptyCell($dates[$idx], $today);
                    $idx++;
                }
                $html .= '</tr>';
            }
        }
        return $html . '</tbody>';
    }

    /** Dialog zum Anlegen/Ändern (nur für Bearbeiter*innen). */
    private function renderDialog(): string {
        $bgOptionen = '';
        foreach ($this->editor['bildungsgaenge'] as $kuerzel) {
            $bgOptionen .= sprintf('<option value="%s">%s</option>', esc_attr($kuerzel), esc_html($kuerzel));
        }
        $etOptionen = '';
        foreach ($this->editor['ereignistypen'] ?? [] as $kuerzel => $anzeige) {
            $etOptionen .= sprintf('<option value="%s">%s</option>', esc_attr($kuerzel), esc_html($anzeige));
        }

        return '
        <dialog class="tt-edit-dialog">
            <form method="dialog" class="tt-edit-form">
                <h3 class="tt-edit-titel">Termin</h3>
                <input type="hidden" name="id" value="">
                <div class="tt-edit-grid">
                    <label>Bildungsgang<select name="bildungsgang" required><option value="">– wählen –</option>' . $bgOptionen . '</select></label>
                    <label>Ereignistyp<select name="ereignistyp" required><option value="">– wählen –</option>' . $etOptionen . '</select></label>
                    <label class="tt-span-2">Bezeichnung<input type="text" name="bezeichnung" maxlength="' . Schema::LEN_TERMIN_BEZEICHNUNG . '" required></label>
                    <label>Von<input type="date" name="beginn" required></label>
                    <label>Bis<input type="date" name="ende" required></label>
                    <label class="tt-span-2">Verantwortlich<input type="text" name="verantwortlich" maxlength="' . Schema::LEN_TERMIN_VERANTWORTLICH . '"></label>
                </div>
                <div class="tt-edit-meldung" role="status" aria-live="polite"></div>
                <div class="tt-edit-aktionen">
                    <button type="button" class="tt-edit-loeschen button-link" hidden>Termin löschen</button>
                    <span class="tt-edit-rechts">
                        <button type="button" class="tt-edit-abbrechen">Abbrechen</button>
                        <button type="submit" class="tt-edit-speichern">Speichern</button>
                    </span>
                </div>
            </form>
        </dialog>';
    }

    /**
     * Verteilt Termine so auf Spuren, dass sich innerhalb einer Spur nichts überlappt
     * (Greedy: jeder Termin kommt in die erste Spur, deren letzter Termin vorher endet).
     *
     * @param Termin[] $termine
     * @return array<int, Termin[]>
     */
    private function verteileAufSpuren(array $termine): array {
        usort($termine, fn($a, $b) => [$a->getBeginn(), $a->getEnde()] <=> [$b->getBeginn(), $b->getEnde()]);

        $lanes = [];
        $laneEnds = [];
        foreach ($termine as $termin) {
            $placed = false;
            foreach ($laneEnds as $i => $end) {
                if ($end < $termin->getBeginn()) {
                    $lanes[$i][] = $termin;
                    $laneEnds[$i] = $termin->getEnde();
                    $placed = true;
                    break;
                }
            }
            if (!$placed) {
                $lanes[] = [$termin];
                $laneEnds[] = $termin->getEnde();
            }
        }
        return $lanes;
    }

    private function renderEmptyCell(DateTime $date, string $today): string {
        $dateStr = $date->format('d.m.y');
        $class = '';
        if ($dateStr === $today) $class = 'today';
        elseif (isset($this->ferien[$dateStr])) $class = 'holiday';
        elseif (in_array($date->format('N'), ['6', '7'])) $class = 'weekend';
        if ($date->format('j') === '1') $class .= ' tt-monatsanfang';
        return '<td class="' . trim($class) . '"></td>';
    }

    private function getUniqueBildungsgaenge(): array {
        $tracks = array_unique(array_map(fn($t) => $t->getBildungsgang(), $this->timetable->getTermine()));
        sort($tracks);
        return $tracks;
    }

    private function getWochentag(DateTime $date): string {
        $map = ['1'=>'Mo','2'=>'Di','3'=>'Mi','4'=>'Do','5'=>'Fr','6'=>'Sa','7'=>'So'];
        return $map[$date->format('N')];
    }
}
