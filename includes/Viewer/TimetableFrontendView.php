<?php
declare(strict_types=1);

namespace MH\Timetable\Viewer;

use MH\Timetable\Model\Entity\Timetable;
use DateTime;

class TimetableFrontendView
{
    private Timetable $timetable;
    private string $entwurf;
    private array $ferien;

    public function __construct(Timetable $timetable, string $entwurf = 'nein', array $ferien = [])
    {
        $this->timetable = $timetable;
        $this->entwurf = $entwurf;
        $this->ferien = $ferien;
    }

    public function render(): string
    {
        if (empty($this->timetable->getTermine())) {
            return '<p style="color:red;font-style:italic;">Keine Termine vorhanden</p>';
        }
        return $this->generiereGantt();
    }

    private function generiereGantt(): string
    {
        $today = (new DateTime())->format('d.m.y');
        $entwurfText = ($this->entwurf === 'ja') ? ' <span style="color:red;"> | ENTWURF</span>' : '';

        $html = '<h2>' . esc_html($this->timetable->getBezeichnung()) . $entwurfText . '</h2>';
        $html .= '<p><b>' . esc_html($this->timetable->getBeschreibung()) . '</b></p>';

        $dates = $this->timetable->getDateRange();

        $html .= '<div class="timetable-container"><table class="timetablegrid">';
        $html .= $this->renderHeader($dates, $today);
        $html .= $this->renderBody($dates, $today);
        $html .= '</table></div>';

        return $html;
    }

    private function renderHeader(array $dates, string $today): string
    {
        $html = '<thead class="timetablegrid_thead"><tr><th class="sticky_column">Bildungsgang</th>';
        foreach ($dates as $date) {
            $dateStr = $date->format('d.m.y');
            $css = "timetable_date";
            if (in_array($date->format('N'), ['6', '7'])) $css .= '_weekend';
            if (isset($this->ferien[$dateStr])) $css .= '_holiday';
            if ($dateStr === $today) $css .= ' timetable_date_today';

            $html .= '<th class="' . $css . '">' . $date->format('d.m.') . '<br>' . $this->getWochentag($date) . '</th>';
        }
        return $html . '</tr></thead>';
    }

    private function renderBody(array $dates, string $today): string
    {
        $html = '<tbody>';
        $grouped = [];
        foreach ($this->timetable->getTermine() as $t) $grouped[$t->getBildungsgang()][] = $t;

        foreach ($grouped as $bg => $bgTermine) {
            // 🟢 iCal Link generieren
            $downloadUrl = add_query_arg([
                'download_ical' => 1,
                'timetable_id'  => $this->timetable->getId(),
                'bg'            => $bg
            ], home_url($_SERVER['REQUEST_URI']));

            $html .= '<tr><td class="sticky_column">' . esc_html($bg) . 
                     ' <a href="' . esc_url($downloadUrl) . '" style="font-size:10px;">(iCal)</a></td>';
            
            $idx = 0;
            foreach ($bgTermine as $termin) {
                while ($idx < count($dates) && $dates[$idx] < $termin->getBeginn()) {
                    $html .= $this->renderEmptyCell($dates[$idx], $today);
                    $idx++;
                }
                if ($idx < count($dates)) {
                    $dauer = $termin->getDauer();
                    $label = esc_html($termin->getEreignistyp());
                    if ($dauer > 6) $label .= ' (<i>' . esc_html($termin->getBezeichnung()) . '</i>)';
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

    private function renderEmptyCell(\DateTime $date, string $today): string
    {
        $dateStr = $date->format('d.m.y');
        $class = '';
        if ($dateStr === $today) $class = 'today';
        elseif (isset($this->ferien[$dateStr])) $class = 'holiday';
        elseif (in_array($date->format('N'), ['6', '7'])) $class = 'weekend';
        return '<td class="' . $class . '"></td>';
    }

    private function getWochentag(\DateTime $date): string {
        $map = ['1'=>'Mo','2'=>'Di','3'=>'Mi','4'=>'Do','5'=>'Fr','6'=>'Sa','7'=>'So'];
        return $map[$date->format('N')];
    }
}