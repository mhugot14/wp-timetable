<?php
declare(strict_types=1);

namespace MH\Timetable\Viewer;

use MH\Timetable\Model\Entity\Timetable;
use DateTime;

class TimetableFrontendView {
    private Timetable $timetable;
    private string $entwurf;
    private array $ferien;

    public function __construct(Timetable $timetable, string $entwurf = 'nein', array $ferien = []) {
        $this->timetable = $timetable;
        $this->entwurf = $entwurf;
        $this->ferien = $ferien;
    }

    public function render(): string {
        $uid = $this->timetable->getId();
        $termine = $this->timetable->getTermine();

        if (empty($termine)) {
            return '<div class="mh-tt-frontend-wrapper"><p>Keine Termine für diese Zeittafel vorhanden.</p></div>';
        }

        $html = '<div class="mh-tt-frontend-wrapper" id="tt-wrapper-' . $uid . '">';
        
        // 1. Controls Row (Filter + Switcher)
        $html .= '<div class="tt-controls-row">';
        
        // Filter
        $html .= '<div class="tt-filter-box">';
        $html .= '<strong>Filter:</strong> ';
        $html .= '<select class="tt-bg-filter">';
        $html .= '<option value="">Alle Bildungsgänge</option>';
        foreach ($this->getUniqueBildungsgaenge() as $bg) {
            $html .= '<option value="' . esc_attr($bg) . '">' . esc_html($bg) . '</option>';
        }
        $html .= '</select></div>';

        // Switcher
        $html .= '
		<div class="tt-view-switcher">
			<button class="tt-switch-btn active" data-view="tt-view-gantt-' . $uid . '"><span class="dashicons dashicons-chart-bar"></span> GANTT</button>
			<button class="tt-switch-btn" data-view="tt-view-list-' . $uid . '"><span class="dashicons dashicons-list-view"></span> Liste</button>
			<button class="tt-switch-btn" data-view="tt-view-month-' . $uid . '"><span class="dashicons dashicons-calendar-alt"></span> Monat</button>
		</div>';
        
        $html .= '</div>'; // Ende tt-controls-row

        // 2. Container für die Ansichten
        $html .= '<div id="tt-view-gantt-' . $uid . '" class="tt-view-container">' . $this->generiereGantt() . '</div>';
        
        $listView = new ListFrontendView($this->timetable, $this->ferien);
        $html .= '<div id="tt-view-list-' . $uid . '" class="tt-view-container" style="display:none;">' . $listView->render() . '</div>';
		
		$monthView = new MonthFrontendView($this->timetable, $this->ferien);
		$html .= '<div id="tt-view-month-' . $uid . '" class="tt-view-container" style="display:none;">' . $monthView->render() . '</div>';
		
        $html .= '</div>'; // Ende wrapper

        // 3. JavaScript (Filter & Switcher)
        if (!wp_script_is('mh-tt-frontend-js', 'done')) {
            $html .= $this->getInlineJs();
            wp_script_add_data('mh-tt-frontend-js', 'done', true);
        }

        return $html;
    }

    private function generiereGantt(): string {
        $today = (new DateTime())->format('d.m.y');
	
	// 🟢 Entwurf-Hinweis in der Überschrift
		$entwurfSuffix = ($this->entwurf === 'ja') 
			? ' <span style="color:#d63638; font-weight:bold; text-transform:uppercase; font-size:0.6em; vertical-align:middle; margin-left:10px;">(Entwurf - noch nicht verbindlich)</span>' 
			: '';
		$html = '<h3>' . esc_html($this->timetable->getBezeichnung()) . $entwurfSuffix . '</h3>';
		$html .= '<p>' . esc_html($this->timetable->getBeschreibung()) . '</p>';

        $dates = $this->timetable->getDateRange();
        $html .= '<div class="timetable-container"><table class="timetablegrid">';
        $html .= $this->renderHeader($dates, $today);
        $html .= $this->renderBody($dates, $today);
        $html .= '</table></div>';

        return $html;
    }

    private function renderHeader(array $dates, string $today): string {
        $html = '<thead class="timetablegrid_thead"><tr><th class="sticky_column">Bildungsgang</th>';
        foreach ($dates as $date) {
            $dateStr = $date->format('d.m.y');
            $css = "timetable_date";
            if (in_array($date->format('N'), ['6', '7'])) $css .= '_weekend';
            if (isset($this->ferien[$dateStr])) $css .= '_holiday';
            if ($dateStr === $today) $css .= ' timetable_date_today';

            $html .= '<th class="' . $css . '"><div class="vertical-header-wrapper">';
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

        foreach ($grouped as $bg => $bgTermine) {
            $downloadUrl = add_query_arg(['download_ical' => 1, 'timetable_id' => $this->timetable->getId(), 'bg' => $bg], home_url($_SERVER['REQUEST_URI']));
            
			// 🟢 iCal-Link nur anzeigen, wenn KEIN Entwurf
			$icalLink = '';
			if ($this->entwurf !== 'ja') {
				$downloadUrl = add_query_arg(['download_ical' => 1, 'timetable_id' => $this->timetable->getId(), 'bg' => $bg], home_url($_SERVER['REQUEST_URI']));
				$icalLink = ' <a href="' . esc_url($downloadUrl) . '" class="ical-small-link">(iCal)</a>';
			}

    $html .= '<td class="sticky_column"><span class="bg-name">' . esc_html($bg) . '</span>' . $icalLink . '</td>';
	
	/*
            $html .= '<tr data-bg="' . esc_attr($bg) . '">';
            $html .= '<td class="sticky_column">' . esc_html($bg) . ' <a href="' . esc_url($downloadUrl) . '" class="ical-small-link">(iCal)</a></td>';
	 * 
	 */
            
            $idx = 0;
            foreach ($bgTermine as $termin) {
                while ($idx < count($dates) && $dates[$idx] < $termin->getBeginn()) {
                    $html .= $this->renderEmptyCell($dates[$idx], $today);
                    $idx++;
                }
                if ($idx < count($dates)) {
                    $dauer = $termin->getDauer();
                    $label = esc_html($termin->getEreignistyp());
                    if ($dauer > 4) $label .= ' (' . esc_html($termin->getBezeichnung()) . ')';
                    $html .= '<td colspan="' . $dauer . '" class="td_' . sanitize_title($termin->getEreignistyp()) . '">' . $label . '</td>';
                    $idx += $dauer;
                }
            }
            while ($idx < count($dates)) {
                $html .= $this->renderEmptyCell($dates[$idx], $today);
                $idx++;
            }
            $html .= '</tr>';
        }
        return $html . '</tbody>';
    }

    private function renderEmptyCell(DateTime $date, string $today): string {
        $dateStr = $date->format('d.m.y');
        $class = '';
        if ($dateStr === $today) $class = 'today';
        elseif (isset($this->ferien[$dateStr])) $class = 'holiday';
        elseif (in_array($date->format('N'), ['6', '7'])) $class = 'weekend';
        return '<td class="' . $class . '"></td>';
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

    private function getInlineJs(): string {
        return '<script>
        document.addEventListener("click", function(e) {
            const btn = e.target.closest(".tt-switch-btn");
            if (!btn) return;
            const wrapper = btn.closest(".mh-tt-frontend-wrapper");
            wrapper.querySelectorAll(".tt-switch-btn").forEach(b => b.classList.remove("active"));
            btn.classList.add("active");
            wrapper.querySelectorAll(".tt-view-container").forEach(c => c.style.display = "none");
            document.getElementById(btn.dataset.view).style.display = "block";
        });
        document.addEventListener("change", function(e) {
            const filter = e.target.closest(".tt-bg-filter");
            if (!filter) return;
            const wrapper = filter.closest(".mh-tt-frontend-wrapper");
            const val = filter.value;
            wrapper.querySelectorAll("[data-bg]").forEach(el => {
                el.style.display = (val === "" || el.dataset.bg === val) ? "" : "none";
            });
            wrapper.querySelectorAll(".tt-list-date-divider").forEach(div => {
                let hasVisible = false;
                let next = div.nextElementSibling;
                while(next && next.classList.contains("tt-list-card")) {
                    if(next.style.display !== "none") { hasVisible = true; break; }
                    next = next.nextElementSibling;
                }
                div.style.display = hasVisible ? "" : "none";
            });
        });
        </script>';
    }
}