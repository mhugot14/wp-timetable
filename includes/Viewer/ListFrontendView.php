<?php
declare(strict_types=1);

namespace MH\Timetable\Viewer;

use MH\Timetable\Model\Entity\Timetable;
use DateTime;

/**
 * Zuständig für die moderne Listenansicht (Cards) im Frontend.
 */
class ListFrontendView
{
    private Timetable $timetable;
    private array $ferien;

    public function __construct(Timetable $timetable, array $ferien = [])
    {
        $this->timetable = $timetable;
        $this->ferien = $ferien;
    }

    public function render(): string
    {
        $termine = $this->timetable->getTermine();
        
        // 1. Termine nach Datum sortieren
        usort($termine, fn($a, $b) => $a->getBeginn() <=> $b->getBeginn());

        $html = '<div class="tt-list-view">';
        
        $currentDate = '';
        foreach ($termine as $termin) {
            $dateLabel = $termin->getBeginn()->format('d.m.Y');
            
            // 2. Datumstrennlinie hinzufügen, wenn ein neuer Tag beginnt
            if ($dateLabel !== $currentDate) {
                $html .= sprintf(
                    '<div class="tt-list-date-divider"><span>%s</span></div>', 
                    $this->getGermanDateLabel($termin->getBeginn())
                );
                $currentDate = $dateLabel;
            }

            // 3. Farbe aus der Taxonomie holen
            $color = $this->getEreignisColor($termin->getEreignistyp());
            
            // 4. Die Card mit dem wichtigen data-bg Attribut für den Filter
            $html .= sprintf(
                '<div class="tt-list-card" data-bg="%s" style="border-left: 5px solid %s;">
                    <div class="tt-list-card-content">
                        <div class="tt-list-card-header">
                            <span class="tt-list-bg">%s</span>
                            <span class="tt-list-type" style="background:%s">%s</span>
                        </div>
                        <h4 class="tt-list-title">%s</h4>
                        <div class="tt-list-meta">
                            <span class="dashicons dashicons-calendar-alt"></span> %s - %s
                            %s
                        </div>
                    </div>
                </div>',
                esc_attr($termin->getBildungsgang()), // data-bg für JS Filter
                $color,
                esc_html($termin->getBildungsgang()),
                $color,
                esc_html($termin->getEreignistyp()),
                esc_html($termin->getBezeichnung()),
                $termin->getBeginn()->format('d.m.'),
                $termin->getEnde()->format('d.m.'),
                $termin->getVerantwortlich() ? ' | <span class="dashicons dashicons-admin-users"></span> ' . esc_html($termin->getVerantwortlich()) : ''
            );
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Hilfsmethode für deutsche Datums-Labels.
     */
    private function getGermanDateLabel(DateTime $date): string
    {
        $days = ['Sunday' => 'Sonntag', 'Monday' => 'Montag', 'Tuesday' => 'Dienstag', 'Wednesday' => 'Mittwoch', 'Thursday' => 'Donnerstag', 'Friday' => 'Freitag', 'Saturday' => 'Samstag'];
        $months = ['January' => 'Januar', 'February' => 'Februar', 'March' => 'März', 'April' => 'April', 'May' => 'Mai', 'June' => 'Juni', 'July' => 'Juli', 'August' => 'August', 'September' => 'September', 'October' => 'Oktober', 'November' => 'November', 'December' => 'Dezember'];
        
        return $days[$date->format('l')] . ', ' . $date->format('d.') . ' ' . $months[$date->format('F')] . ' ' . $date->format('Y');
    }

    /**
     * Holt die Farbe eines Ereignistyps aus den Taxonomie-Metadaten.
     */
    private function getEreignisColor(string $typeName): string
    {
        $term = get_term_by('name', $typeName, 'ereignistyp');
        if ($term && !is_wp_error($term)) {
            $color = get_term_meta($term->term_id, 'ereignisfarbe', true);
            return $color ?: '#cccccc';
        }
        return '#cccccc';
    }
}