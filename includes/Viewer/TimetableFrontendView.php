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
        
                // Ersetze den Bereich der tt-filter-box durch diesen Code:
         $html .= '<div class="tt-filter-box">';
         $html .= '<strong>Filter:</strong> ';
         $html .= '<div class="tt-multiselect-container">';
         $html .= '<label class="tt-select-all-label"><input type="checkbox" class="tt-select-all" checked> <em>Alle auswählen</em></label>';

         foreach ($this->getUniqueBildungsgaenge() as $bg) {
             $bg_slug = trim((string)$bg);
             $html .= sprintf(
                 '<label><input type="checkbox" class="tt-bg-checkbox" value="%s" checked> %s</label>',
                 esc_attr($bg_slug),
                 esc_html($bg)
             );
         }
         $html .= '</div></div>';

        // Switcher
        $html .= '
		<div class="tt-view-switcher">
			<button class="tt-switch-btn active" data-view="tt-view-gantt-' . $uid . '"><span class="dashicons dashicons-chart-bar"></span> GANTT</button>
			<button class="tt-switch-btn" data-view="tt-view-list-' . $uid . '"><span class="dashicons dashicons-list-view"></span> Liste</button>
			<button class="tt-switch-btn" data-view="tt-view-month-' . $uid . '"><span class="dashicons dashicons-calendar-alt"></span> Monat</button>
		</div>';
        
        $html .= '</div>'; // Ende tt-controls-row
        // 🟢 Entwurf-Hinweis in der Überschrift
	$entwurfSuffix = ($this->entwurf === 'ja') 
			? ' <span style="color:#d63638; font-weight:bold; text-transform:uppercase; font-size:0.6em; vertical-align:middle; margin-left:10px;">(Entwurf - noch nicht verbindlich)</span>' 
			: '';
	$html .= '<h3>' . esc_html($this->timetable->getBezeichnung()) . $entwurfSuffix . '</h3>';
	$html .= '<p>' . esc_html($this->timetable->getBeschreibung()) . '</p>';
        // 2. Container für die Ansichten
        $html .= '<div id="tt-view-gantt-' . $uid . '" class="tt-view-container">' . $this->generiereGantt() . '</div>';
        
        $listView = new ListFrontendView($this->timetable, $this->ferien);
        $html .= '<div id="tt-view-list-' . $uid . '" class="tt-view-container" style="display:none;">' . $listView->render() . '</div>';
		
		$monthView = new MonthFrontendView($this->timetable, $this->ferien);
		$html .= '<div id="tt-view-month-' . $uid . '" class="tt-view-container" style="display:none;">' . $monthView->render() . '</div>';
		
        $html .= '</div>'; // Ende wrapper

        // 3. JavaScript (Filter & Switcher)
		$html .= $this->getInlineJs(); // Einfach immer ausgeben
		return $html;
    }

    private function generiereGantt(): string {
        $today = (new DateTime())->format('d.m.y');
	
        $dates = $this->timetable->getDateRange();
        $html = '<div class="timetable-container"><table class="timetablegrid">';
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

			$rangeStart = $dates[0] ?? null;
			$dayCount = count($dates);

			foreach ($grouped as $bg => $bgTermine) {
				// 1. iCal-Link Logik (Nur wenn kein Entwurf)
				$icalLink = '';
				if ($this->entwurf !== 'ja') {
					// Ohne zweites Argument nutzt add_query_arg die aktuelle Request-URI
					// (relativ) – das funktioniert auch in Unterverzeichnis-Installationen.
					$downloadUrl = add_query_arg([
						'download_ical' => 1,
						'timetable_id' => $this->timetable->getId(),
						'bg' => $bg
					]);

					$icalLink = ' <a href="' . esc_url($downloadUrl) . '" class="ical-small-link">(iCal)</a>';
				}

				// 2. Filter-Slug vorbereiten (trim gegen Leerzeichen-Fehler)
				$bg_slug = trim((string)$bg);

				// 3. Überlappende Termine auf mehrere Spuren (Zeilen) verteilen
				$lanes = $this->verteileAufSpuren($bgTermine);
				$laneCount = count($lanes);

				foreach ($lanes as $laneIdx => $laneTermine) {
					$rowClass = $laneIdx > 0 ? ' class="tt-lane-continued"' : '';
					$html .= '<tr data-bg="' . esc_attr($bg_slug) . '"' . $rowClass . '>';
					if ($laneIdx === 0) {
						$rowspan = $laneCount > 1 ? ' rowspan="' . $laneCount . '"' : '';
						$html .= '<td class="sticky_column"' . $rowspan . '><span class="bg-name">' . esc_html($bg) . '</span>' . $icalLink . '</td>';
					}

					// 4. Zeitstrahl-Zellen rendern
					$idx = 0;
					foreach ($laneTermine as $termin) {
						$startIdx = $rangeStart ? (int)$rangeStart->diff($termin->getBeginn())->days : 0;

						// Leere Zellen vor dem Termin
						while ($idx < $startIdx && $idx < $dayCount) {
							$html .= $this->renderEmptyCell($dates[$idx], $today);
							$idx++;
						}

						// Der Termin-Balken (nie über das Tabellenende hinaus)
						if ($idx < $dayCount) {
							$dauer = max(1, min($termin->getDauer(), $dayCount - $idx));
							$label = esc_html($termin->getEreignistyp());
							if ($dauer > 4) {
								$label .= ' (' . esc_html($termin->getBezeichnung()) . ')';
							}
							$title = sprintf('%s: %s (%s – %s)',
								$termin->getEreignistyp(),
								$termin->getBezeichnung(),
								$termin->getBeginn()->format('d.m.'),
								$termin->getEnde()->format('d.m.')
							);

							$html .= '<td colspan="' . $dauer . '" class="td_' . sanitize_title($termin->getEreignistyp()) . '" title="' . esc_attr($title) . '">' . $label . '</td>';
							$idx += $dauer;
						}
					}

					// Restliche leere Zellen nach den Terminen
					while ($idx < $dayCount) {
						$html .= $this->renderEmptyCell($dates[$idx], $today);
						$idx++;
					}
					$html .= '</tr>';
				}
			}
			return $html . '</tbody>';
		}

    /**
     * Verteilt Termine so auf Spuren, dass sich innerhalb einer Spur nichts überlappt
     * (Greedy: jeder Termin kommt in die erste Spur, deren letzter Termin vorher endet).
     *
     * @param \MH\Timetable\Model\Entity\Termin[] $termine
     * @return array<int, \MH\Timetable\Model\Entity\Termin[]>
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
    document.addEventListener("DOMContentLoaded", function() {
        
        // 1. Multi-Filter Logik
        document.addEventListener("change", function(e) {
            const checkbox = e.target.closest(".tt-bg-checkbox, .tt-select-all");
            if (!checkbox) return;

            const wrapper = checkbox.closest(".mh-tt-frontend-wrapper");
            const allCheckboxes = wrapper.querySelectorAll(".tt-bg-checkbox");
            const selectAllCheckbox = wrapper.querySelector(".tt-select-all");
            
            // --- Logik für "Alle auswählen" ---
            if (checkbox.classList.contains("tt-select-all")) {
                allCheckboxes.forEach(cb => cb.checked = checkbox.checked);
            } else {
                // 🟢 FIX: Prüfen, ob "Alle auswählen" deaktiviert werden muss
                const allChecked = Array.from(allCheckboxes).every(cb => cb.checked);
                if (selectAllCheckbox) {
                    selectAllCheckbox.checked = allChecked;
                }
            }

            // Aktive Bildungsgänge sammeln
            const checkedBoxes = wrapper.querySelectorAll(".tt-bg-checkbox:checked");
            const selectedValues = Array.from(checkedBoxes).map(cb => cb.value.trim());

            // Alle Elemente mit [data-bg] filtern
            wrapper.querySelectorAll("[data-bg]").forEach(el => {
                const bgValue = el.dataset.bg.trim();
                if (selectedValues.includes(bgValue)) {
                    el.classList.remove("tt-hidden");
                } else {
                    el.classList.add("tt-hidden");
                }
            });

            // Divider in der Liste fixen
            wrapper.querySelectorAll(".tt-list-date-divider").forEach(div => {
                let hasVisible = false;
                let next = div.nextElementSibling;
                while(next && next.classList.contains("tt-list-card")) {
                    if(!next.classList.contains("tt-hidden")) { hasVisible = true; break; }
                    next = next.nextElementSibling;
                }
                if(hasVisible) div.classList.remove("tt-hidden");
                else div.classList.add("tt-hidden");
            });
        });
              // 2. Switcher-Logik (Umschalten GANTT / Liste / Monat)
              document.addEventListener("click", function(e) {
                  const btn = e.target.closest(".tt-switch-btn");
                  if (!btn) return;

                  const wrapper = btn.closest(".mh-tt-frontend-wrapper");
                  wrapper.querySelectorAll(".tt-switch-btn").forEach(b => b.classList.remove("active"));
                  btn.classList.add("active");

                  wrapper.querySelectorAll(".tt-view-container").forEach(c => c.style.display = "none");
                  const target = document.getElementById(btn.dataset.view);
                  if (target) target.style.display = "block";
              });
          });
          </script>';
      }
}