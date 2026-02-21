<?php
declare(strict_types=1);

namespace MH\Timetable\Viewer;

use MH\Timetable\Model\Entity\Timetable;
use MH\Timetable\Model\Entity\Termin;
use DateTime;
use DateInterval;
use DatePeriod;

class MonthFrontendView {
    private Timetable $timetable;
    private array $ferien;

    public function __construct(Timetable $timetable, array $ferien = []) {
        $this->timetable = $timetable;
        $this->ferien = $ferien;
    }

    public function render(): string {
        $start = $this->timetable->getEarliestDate();
        $end = $this->timetable->getLastDate();

        if (!$start || !$end) return '';

        // Alle Monate im Zeitraum ermitteln
        $periodStart = new DateTime($start->format('Y-m-01'));
        $periodEnd = new DateTime($end->format('Y-m-01'));
        $periodEnd->modify('+1 month');
        
        $interval = new DateInterval('P1M');
        $monthPeriod = new DatePeriod($periodStart, $interval, $periodEnd);

        $html = '<div class="tt-month-view-container">';
        
        foreach ($monthPeriod as $monthDate) {
            $html .= $this->renderMonth($monthDate);
        }

        $html .= '</div>';
        return $html;
    }

    private function renderMonth(DateTime $monthDate): string {
        $monthName = $this->getGermanMonth($monthDate->format('F'));
        $year = $monthDate->format('Y');
        
        $firstDayOfMonth = new DateTime($monthDate->format('Y-m-01'));
        $lastDayOfMonth = new DateTime($monthDate->format('Y-m-t'));
        
        // Wochentag des ersten Tages (1 = Mo, 7 = So)
        $startDayOfWeek = (int)$firstDayOfMonth->format('N');
        
        $html = '<div class="tt-month-card">';
        $html .= sprintf('<h3 class="tt-month-title">%s %s</h3>', $monthName, $year);
        $html .= '<div class="tt-month-grid">';
        
        // Wochentags-Header
        foreach (['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'] as $day) {
            $html .= '<div class="tt-month-day-header">' . $day . '</div>';
        }

        // Leere Zellen am Anfang
        for ($i = 1; $i < $startDayOfWeek; $i++) {
            $html .= '<div class="tt-month-day empty"></div>';
        }

        // Tage des Monats
        $daysInMonth = (int)$monthDate->format('t');
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $currentDate = new DateTime($monthDate->format("Y-m-$day"));
            $dateStr = $currentDate->format('d.m.y');
            
            $classes = ['tt-month-day'];
            if (in_array($currentDate->format('N'), ['6', '7'])) $classes[] = 'is-weekend';
            if (isset($this->ferien[$dateStr])) $classes[] = 'is-holiday';
            if ($currentDate->format('Y-m-d') === date('Y-m-d')) $classes[] = 'is-today';

            $html .= sprintf('<div class="%s"><span class="day-number">%d</span>', implode(' ', $classes), $day);
            
            // Termine für diesen Tag suchen
            $html .= $this->renderEventsForDay($currentDate);
            
            $html .= '</div>';
        }

        $html .= '</div></div>';
        return $html;
    }

	private function renderEventsForDay(DateTime $date): string {
		$html = '<div class="day-events">';
		foreach ($this->timetable->getTermine() as $termin) {
			if ($date >= $termin->getBeginn() && $date <= $termin->getEnde()) {
				$color = $this->getEreignisColor($termin->getEreignistyp());
				$isStart = $date->format('Y-m-d') === $termin->getBeginn()->format('Y-m-d');

				// DIESE ZEILE WURDE GEÄNDERT: data-bg hinzugefügt
				$html .= sprintf(
					'<div class="day-event-bar %s" style="background:%s" data-bg="%s" title="%s | %s: %s">',
					$isStart ? 'has-title' : 'is-continuation',
					$color,
					esc_attr($termin->getBildungsgang()), // Der Anker für den JS-Filter
					esc_attr($termin->getEreignistyp()),
					esc_attr($termin->getBildungsgang()),
					esc_attr($termin->getBezeichnung())
				);

				if ($isStart) {
					$html .= '<span class="event-content">';
					$html .= '<strong>' . esc_html($termin->getBildungsgang()) . '</strong>: ';
					$html .= esc_html($termin->getEreignistyp());
					$html .= ' <span class="event-subtext">(' . esc_html($termin->getBezeichnung()) . ')</span>';
					$html .= '</span>';
				} else {
					$html .= '<span class="event-content-mini">' . esc_html($termin->getBildungsgang()) . '</span>';
				}

				$html .= '</div>';
			}
		}
		$html .= '</div>';
		return $html;
	}

    private function getEreignisColor(string $typeName): string {
        $term = get_term_by('name', $typeName, 'ereignistyp');
        return ($term && !is_wp_error($term)) ? get_term_meta($term->term_id, 'ereignisfarbe', true) ?: '#ccc' : '#ccc';
    }

    private function getGermanMonth(string $month): string {
        $map = ['January'=>'Januar','February'=>'Februar','March'=>'März','April'=>'April','May'=>'Mai','June'=>'Juni','July'=>'Juli','August'=>'August','September'=>'September','October'=>'Oktober','November'=>'November','December'=>'Dezember'];
        return $map[$month] ?? $month;
    }
}