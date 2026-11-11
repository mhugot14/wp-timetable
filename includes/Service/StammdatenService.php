<?php
declare(strict_types=1);

namespace MH\Timetable\Service;

use MH\Timetable\Model\Entity\Bildungsgang;
use MH\Timetable\Model\Entity\Ereignistyp;
use MH\Timetable\Model\Repository\WpdbBildungsgangRepository;
use MH\Timetable\Model\Repository\WpdbEreignistypRepository;

/**
 * Zugriff auf die Stammdaten Bildungsgänge und Ereignistypen
 * (seit 0.9.0 eigene Tabellen statt WordPress-Taxonomien).
 */
class StammdatenService
{
    private WpdbBildungsgangRepository $bildungsgaenge;
    private WpdbEreignistypRepository $ereignistypen;

    public function __construct(WpdbBildungsgangRepository $bildungsgaenge, WpdbEreignistypRepository $ereignistypen)
    {
        $this->bildungsgaenge = $bildungsgaenge;
        $this->ereignistypen = $ereignistypen;
    }

    public function bildungsgaenge(): WpdbBildungsgangRepository { return $this->bildungsgaenge; }
    public function ereignistypen(): WpdbEreignistypRepository { return $this->ereignistypen; }

    /** @return Bildungsgang[] */
    public function getBildungsgaenge(): array { return $this->bildungsgaenge->getAll(); }

    /** @return Ereignistyp[] */
    public function getEreignistypen(): array { return $this->ereignistypen->getAll(); }

    /** @return array<string,string> Ereignistyp-Kürzel → Farbe */
    public function getFarbMap(): array
    {
        $map = [];
        foreach ($this->ereignistypen->getAll() as $et) {
            $map[$et->getKuerzel()] = $et->getFarbe();
        }
        return $map;
    }

    /** @return array<string,string> Ereignistyp-Kürzel → Bezeichnung (für Legende/Tooltips) */
    public function getBezeichnungMap(): array
    {
        $map = [];
        foreach ($this->ereignistypen->getAll() as $et) {
            $map[$et->getKuerzel()] = $et->getBezeichnung();
        }
        return $map;
    }

    /** @return array<string,string> Bildungsgang-Kürzel → Bezeichnung */
    public function getBildungsgangBezeichnungMap(): array
    {
        $map = [];
        foreach ($this->bildungsgaenge->getAll() as $bg) {
            $map[$bg->getKuerzel()] = $bg->getBezeichnung();
        }
        return $map;
    }

    /** Farbklassen td_<slug> für das GANTT (Frontend und Backend). */
    public function generateDynamicCss(): void
    {
        $farben = $this->getFarbMap();
        if (empty($farben)) return;

        echo '<style id="mh-timetable-dynamic-css">';
        foreach ($farben as $kuerzel => $farbe) {
            $slug = sanitize_title($kuerzel); // identisch zur Klasse im GANTT (td_<slug>)
            echo ".td_{$slug} { background-color: {$farbe} !important; color: white; }\n";
        }
        echo '</style>';
    }

    /**
     * <option>-Liste für Auswahlfelder (Wert = Kürzel). Inaktive Einträge stehen
     * am Ende und sind markiert – so bleibt bei alten Terminen die Auswahl erhalten.
     *
     * @param Bildungsgang[]|Ereignistyp[] $eintraege
     */
    public static function optionen(array $eintraege, string $ausgewaehlt = ''): string
    {
        $html = '';
        foreach ($eintraege as $e) {
            $html .= sprintf(
                '<option value="%s"%s>%s%s</option>',
                esc_attr($e->getKuerzel()),
                selected($ausgewaehlt, $e->getKuerzel(), false),
                esc_html($e->getKuerzel()),
                $e->isAktiv() ? '' : ' (inaktiv)'
            );
        }
        return $html;
    }
}
