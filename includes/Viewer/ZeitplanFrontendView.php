<?php
declare(strict_types=1);

namespace MH\Timetable\Viewer;

use MH\Timetable\Model\Entity\Termin;
use MH\Timetable\Model\Entity\Timetable;
use MH\Timetable\Service\IcalService;
use DateTime;

/**
 * „Zeitplan“ – die neu gedachte GANTT-Ansicht.
 *
 *  - Lesbare Beschriftung (Ereignistyp ausgeschrieben + Bezeichnung) direkt am
 *    Balken; passt der Text nicht hinein, steht er rechts daneben.
 *  - Eintägige Termine als Raute (Stichtag), mehrtägige als Balken (Zeitraum).
 *  - Zeitskala Monat / Kalenderwoche / Tag, Ferien als beschriftetes Band,
 *    Wochenenden und Ferien schattiert, „Heute“-Linie.
 *  - Spuren je Bildungsgang so verteilt, dass sich weder Balken noch Texte
 *    überlappen.
 *
 * Aufgebaut aus absolut positionierten Elementen (kein Tabellenraster), dadurch
 * frei skalierbar und mit transform verschiebbar (Drag & Drop im Editor).
 */
class ZeitplanFrontendView
{
    private const TAG_PX = 28;        // Breite eines Tages
    private const SPUR_PX = 30;       // Höhe einer Spur
    private const LABEL_SPALTE = 190; // Breite der Bildungsgang-Spalte
    private const MONATE = [1 => 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
    private const WOCHENTAGE = [1 => 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];

    private Timetable $timetable;
    /** @var array<string,string> d.m.y → Ferienname */
    private array $ferien;
    /** @var array<string,string> Ereignistyp-Kürzel → Farbe */
    private array $farben;
    /** @var array<string,string> Ereignistyp-Kürzel → Bezeichnung */
    private array $typNamen;
    /** @var array<string,string> Bildungsgang-Kürzel → Bezeichnung */
    private array $bgNamen;
    /** @var string[] */
    private array $editierbareBgs;
    private bool $entwurf;

    public function __construct(Timetable $timetable, array $ferien, array $farben, array $typNamen, array $bgNamen, array $editierbareBgs, bool $entwurf)
    {
        $this->timetable = $timetable;
        $this->ferien = $ferien;
        $this->farben = $farben;
        $this->typNamen = $typNamen;
        $this->bgNamen = $bgNamen;
        $this->editierbareBgs = $editierbareBgs;
        $this->entwurf = $entwurf;
    }

    public function render(): string
    {
        $termine = $this->timetable->getTermine();
        if (!$termine) return '';

        // Zeitraum: vom Montag der ersten bis zum Sonntag der letzten Woche,
        // plus eine Woche Luft für Beschriftungen rechts neben späten Terminen.
        $start = (clone $this->timetable->getEarliestDate())->modify('monday this week');
        $ende = (clone $this->timetable->getLastDate())->modify('sunday this week')->modify('+7 days');
        $tage = [];
        for ($d = clone $start; $d <= $ende; $d->modify('+1 day')) {
            $tage[] = clone $d;
        }
        $breite = count($tage) * self::TAG_PX;

        $heuteIdx = null;
        $heute = (new DateTime('today'))->format('Y-m-d');
        foreach ($tage as $i => $t) {
            if ($t->format('Y-m-d') === $heute) $heuteIdx = $i;
        }

        $html = sprintf(
            '<div class="tt-zp" style="--tt-tag:%dpx; --tt-spur:%dpx; --tt-label:%dpx;">',
            self::TAG_PX, self::SPUR_PX, self::LABEL_SPALTE
        );
        $html .= sprintf('<div class="tt-zp-scroll"%s>', $heuteIdx !== null ? ' data-heute-px="' . ($heuteIdx * self::TAG_PX) . '"' : '');
        $html .= sprintf('<div class="tt-zp-inner" style="width:%dpx">', self::LABEL_SPALTE + $breite);

        $html .= $this->renderKopf($tage, $breite);
        $html .= '<div class="tt-zp-body">';
        $html .= $this->renderRaster($tage, $breite, $heuteIdx);
        $html .= $this->renderZeilen($termine, $start, $breite);
        $html .= '</div>'; // body

        $html .= '</div></div></div>';
        return $html;
    }

    // ---------------------------------------------------------------- Kopf

    /** @param DateTime[] $tage */
    private function renderKopf(array $tage, int $breite): string
    {
        $monate = '';
        $wochen = '';
        $tagZeile = '';
        $ferienBaender = '';

        $laufMonat = null; $monatStart = 0;
        $laufFerien = null; $ferienStart = 0;
        $n = count($tage);

        foreach ($tage as $i => $t) {
            // Monate
            $m = $t->format('Y-n');
            if ($m !== $laufMonat) {
                if ($laufMonat !== null) $monate .= $this->monatsBlock($laufMonat, $monatStart, $i);
                $laufMonat = $m; $monatStart = $i;
            }
            // Kalenderwochen (beginnen montags)
            if ($t->format('N') === '1') {
                $wochen .= sprintf(
                    '<div class="tt-zp-woche" style="left:%dpx; width:%dpx">KW %d</div>',
                    $i * self::TAG_PX, 7 * self::TAG_PX, (int)$t->format('W')
                );
            }
            // Tage
            $klassen = ['tt-zp-tag'];
            if ((int)$t->format('N') >= 6) $klassen[] = 'is-wochenende';
            if (isset($this->ferien[$t->format('d.m.y')])) $klassen[] = 'is-ferien';
            if ($t->format('Y-m-d') === (new DateTime('today'))->format('Y-m-d')) $klassen[] = 'is-heute';
            $tagZeile .= sprintf(
                '<div class="%s" style="left:%dpx"><span>%s</span>%s</div>',
                implode(' ', $klassen), $i * self::TAG_PX, self::WOCHENTAGE[(int)$t->format('N')], $t->format('j')
            );
            // Ferienbänder (zusammenhängende Tage mit gleichem Namen)
            $f = $this->ferien[$t->format('d.m.y')] ?? null;
            if ($f !== $laufFerien) {
                if ($laufFerien !== null) $ferienBaender .= $this->ferienBand($laufFerien, $ferienStart, $i);
                $laufFerien = $f; $ferienStart = $i;
            }
        }
        $monate .= $this->monatsBlock((string)$laufMonat, $monatStart, $n);
        if ($laufFerien !== null) $ferienBaender .= $this->ferienBand($laufFerien, $ferienStart, $n);

        return '<div class="tt-zp-kopf">'
            . '<div class="tt-zp-ecke">Bildungsgang</div>'
            . sprintf('<div class="tt-zp-skala" style="width:%dpx">', $breite)
            . '<div class="tt-zp-reihe tt-zp-reihe-monat">' . $monate . '</div>'
            . '<div class="tt-zp-reihe tt-zp-reihe-woche">' . $wochen . $ferienBaender . '</div>'
            . '<div class="tt-zp-reihe tt-zp-reihe-tag">' . $tagZeile . '</div>'
            . '</div></div>';
    }

    private function monatsBlock(string $jahrMonat, int $von, int $bis): string
    {
        [$jahr, $monat] = explode('-', $jahrMonat);
        $tage = $bis - $von;
        $label = $tage >= 6 ? self::MONATE[(int)$monat] . ' ' . $jahr : mb_substr(self::MONATE[(int)$monat], 0, 3) . '.';
        return sprintf('<div class="tt-zp-monat" style="left:%dpx; width:%dpx"><span>%s</span></div>', $von * self::TAG_PX, $tage * self::TAG_PX, esc_html($label));
    }

    private function ferienBand(string $name, int $von, int $bis): string
    {
        return sprintf(
            '<div class="tt-zp-ferienband" style="left:%dpx; width:%dpx" title="%s"><span>%s</span></div>',
            $von * self::TAG_PX, ($bis - $von) * self::TAG_PX, esc_attr($name), esc_html($name)
        );
    }

    // -------------------------------------------------------------- Raster

    /** Hintergrund: Wochenenden, Ferien, Wochenanfänge, Heute – einmal für alle Zeilen. */
    private function renderRaster(array $tage, int $breite, ?int $heuteIdx): string
    {
        $html = sprintf('<div class="tt-zp-raster" style="width:%dpx">', $breite);
        foreach ($tage as $i => $t) {
            $klassen = [];
            if (isset($this->ferien[$t->format('d.m.y')])) $klassen[] = 'is-ferien';
            elseif ((int)$t->format('N') >= 6) $klassen[] = 'is-wochenende';
            if ($t->format('N') === '1') $klassen[] = 'is-wochenstart';
            if ($klassen) {
                $html .= sprintf('<div class="tt-zp-raster-tag %s" style="left:%dpx"></div>', implode(' ', $klassen), $i * self::TAG_PX);
            }
        }
        if ($heuteIdx !== null) {
            $html .= sprintf('<div class="tt-zp-heute" style="left:%dpx" title="Heute"><span>Heute</span></div>', $heuteIdx * self::TAG_PX + intdiv(self::TAG_PX, 2));
        }
        return $html . '</div>';
    }

    // -------------------------------------------------------------- Zeilen

    /** @param Termin[] $termine */
    private function renderZeilen(array $termine, DateTime $start, int $breite): string
    {
        $gruppen = [];
        foreach ($termine as $t) {
            $gruppen[$t->getBildungsgang()][] = $t;
        }
        uksort($gruppen, 'strnatcasecmp');

        $ttId = (int)$this->timetable->getId();
        $html = '';
        foreach ($gruppen as $bg => $bgTermine) {
            $bg = (string)$bg;
            $editierbar = in_array($bg, $this->editierbareBgs, true);
            [$platziert, $spuren] = $this->layout($bgTermine, $start);

            $label = '<div class="tt-zp-label"><div class="tt-zp-bg">' . esc_html($bg);
            if ($editierbar) {
                $label .= sprintf(' <button type="button" class="tt-add-btn tt-add-inline" data-bg="%s" title="Termin für %s anlegen">+</button>', esc_attr($bg), esc_attr($bg));
            }
            $label .= '</div>';
            $langname = trim($this->bgNamen[$bg] ?? '');
            if ($langname !== '' && mb_strtolower($langname) !== mb_strtolower($bg)) {
                $label .= '<div class="tt-zp-bg-lang" title="' . esc_attr($langname) . '">' . esc_html($langname) . '</div>';
            }
            if (!$this->entwurf) {
                $label .= sprintf(
                    '<div class="tt-zp-links"><a href="%s" title="Termine von %s als .ics herunterladen">iCal</a> · <a href="%s" title="Termine von %s im Kalender abonnieren">Abo</a></div>',
                    esc_url(IcalService::buildUrl($ttId, $bg)), esc_attr($bg),
                    esc_url(IcalService::buildUrl($ttId, $bg, true)), esc_attr($bg)
                );
            }
            $label .= '</div>';

            $balken = '';
            foreach ($platziert as $p) {
                $balken .= $this->renderBalken($p, $editierbar);
            }

            $html .= sprintf(
                '<div class="tt-zp-zeile" data-bg="%s" style="height:%dpx">%s<div class="tt-zp-spur" style="width:%dpx">%s</div></div>',
                esc_attr(trim($bg)),
                max(1, $spuren) * self::SPUR_PX + 8,
                $label,
                $breite,
                $balken
            );
        }
        return $html;
    }

    /**
     * Berechnet Position, Beschriftung und Spur je Termin. Ein Termin belegt
     * horizontal seinen Balken plus – falls die Beschriftung nicht hineinpasst –
     * den Text rechts daneben. Spuren werden „greedy“ vergeben.
     *
     * @param Termin[] $termine
     * @return array{0: array<int,array>, 1: int}
     */
    private function layout(array $termine, DateTime $start): array
    {
        usort($termine, fn(Termin $a, Termin $b) => [$a->getBeginn(), $a->getEnde()] <=> [$b->getBeginn(), $b->getEnde()]);

        $spurEnden = [];
        $ergebnis = [];
        foreach ($termine as $t) {
            $links = (int)$start->diff($t->getBeginn())->days * self::TAG_PX;
            $tage = max(1, $t->getDauer());
            $breite = $tage * self::TAG_PX;
            $stichtag = $tage === 1;

            [$haupt, $neben] = $this->beschriftung($t);
            $textPx = $this->textBreite($haupt . ($neben !== '' ? ' · ' . $neben : ''));
            $innen = !$stichtag && $textPx + 16 <= $breite;
            $belegtBis = $links + $breite + ($innen ? 0 : 6 + $textPx) + 10;

            $spur = 0;
            while (isset($spurEnden[$spur]) && $spurEnden[$spur] > $links) {
                $spur++;
            }
            $spurEnden[$spur] = $belegtBis;

            $ergebnis[] = compact('t', 'links', 'breite', 'stichtag', 'haupt', 'neben', 'innen', 'spur');
        }
        return [$ergebnis, count($spurEnden)];
    }

    /** @return array{0:string,1:string} [ausgeschriebener Typ, Bezeichnung (falls zusätzlich informativ)] */
    private function beschriftung(Termin $t): array
    {
        $typ = $t->getEreignistyp();
        $haupt = trim($this->typNamen[$typ] ?? '') ?: $typ;
        $haupt = rtrim($haupt, '.');
        $bez = trim($t->getBezeichnung());
        // Bezeichnung nur zeigen, wenn sie mehr sagt als der Typ
        $neben = ($bez !== '' && mb_stripos($bez, $haupt) === false && mb_strtolower($bez) !== mb_strtolower($typ)) ? $bez : '';
        return [$haupt, $neben];
    }

    /** Grobe Schätzung der Textbreite (12–13px Systemschrift) */
    private function textBreite(string $text): int
    {
        return (int)ceil(mb_strlen($text) * 6.6);
    }

    private function renderBalken(array $p, bool $editierbar): string
    {
        /** @var Termin $t */
        $t = $p['t'];
        $farbe = $this->farben[$t->getEreignistyp()] ?? '#8c8f94';
        $textFarbe = self::kontrastFarbe($farbe);

        $zeitraum = $p['stichtag']
            ? self::WOCHENTAGE[(int)$t->getBeginn()->format('N')] . ' ' . $t->getBeginn()->format('d.m.Y')
            : self::WOCHENTAGE[(int)$t->getBeginn()->format('N')] . ' ' . $t->getBeginn()->format('d.m.') . ' – '
              . self::WOCHENTAGE[(int)$t->getEnde()->format('N')] . ' ' . $t->getEnde()->format('d.m.Y');
        $tooltip = $p['haupt'] . ($t->getEreignistyp() !== $p['haupt'] ? ' (' . $t->getEreignistyp() . ')' : '')
            . ($t->getBezeichnung() !== '' ? ': ' . $t->getBezeichnung() : '')
            . "\n" . $zeitraum
            . ($t->getVerantwortlich() !== '' ? "\nVerantwortlich: " . $t->getVerantwortlich() : '');

        $text = '<strong>' . esc_html($p['haupt']) . '</strong>' . ($p['neben'] !== '' ? '<span class="tt-zp-neben"> · ' . esc_html($p['neben']) . '</span>' : '');

        $klassen = ['tt-zp-balken', 'tt-bar', $p['stichtag'] ? 'is-stichtag' : 'is-zeitraum', $p['innen'] ? 'text-innen' : 'text-aussen'];
        if ($editierbar) $klassen[] = 'tt-editierbar';

        return sprintf(
            '<div class="%s" style="left:%dpx; width:%dpx; top:%dpx; --tt-farbe:%s; --tt-textfarbe:%s" title="%s" aria-label="%s" data-termin-id="%d"%s>'
            . '%s<span class="tt-zp-text">%s</span></div>',
            implode(' ', $klassen),
            $p['links'],
            $p['breite'],
            $p['spur'] * self::SPUR_PX + 4,
            esc_attr($farbe),
            esc_attr($textFarbe),
            esc_attr($tooltip),
            esc_attr(str_replace("\n", ', ', $tooltip)),
            (int)$t->getId(),
            $editierbar ? sprintf(' tabindex="0" role="button" data-beginn="%s" data-ende="%s"', $t->getBeginn()->format('Y-m-d'), $t->getEnde()->format('Y-m-d')) : '',
            $p['stichtag'] ? '<span class="tt-zp-raute" aria-hidden="true"></span>' : '',
            $text
        );
    }

    /** Schwarz oder Weiß – je nachdem, was auf der Farbe besser lesbar ist (WCAG-Luminanz). */
    public static function kontrastFarbe(string $hex): string
    {
        if (!preg_match('/^#?([0-9a-f]{6})$/i', $hex, $m)) return '#ffffff';
        $kanal = function (string $h): float {
            $c = hexdec($h) / 255;
            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };
        $l = 0.2126 * $kanal(substr($m[1], 0, 2)) + 0.7152 * $kanal(substr($m[1], 2, 2)) + 0.0722 * $kanal(substr($m[1], 4, 2));
        // Kontrast zu Weiß vs. zu Schwarz vergleichen
        return (1.05 / ($l + 0.05)) >= (($l + 0.05) / 0.05) ? '#ffffff' : '#1d2327';
    }
}
