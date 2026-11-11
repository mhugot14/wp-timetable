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
    /** @var array<int,array{name:string,start:string,ende:string,feiertag:bool}> Ferien-Zeiträume für die Bänder */
    private array $ferienListe;

    /** Mindestens so viel Text muss sichtbar bleiben, bevor statt Kürzen eine neue Spur entsteht */
    private const MIN_TEXT_PX = 70;

    public function __construct(Timetable $timetable, array $ferien, array $farben, array $typNamen, array $bgNamen, array $editierbareBgs, bool $entwurf, array $ferienListe = [])
    {
        $this->ferienListe = $ferienListe;
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
        $feiertage = $this->feiertagsMap();
        $mitListe = !empty($this->ferienListe);
        if ($mitListe) {
            $ferienBaender = $this->ferienBaenderAusListe($tage[0], $n);
        }

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
            $feiertag = $feiertage[$t->format('Y-m-d')] ?? null;
            if ($feiertag !== null) $klassen[] = 'is-feiertag';
            if ($t->format('Y-m-d') === (new DateTime('today'))->format('Y-m-d')) $klassen[] = 'is-heute';
            $titel = $feiertag ?? ($this->ferien[$t->format('d.m.y')] ?? '');
            $tagZeile .= sprintf(
                '<div class="%s" style="left:%dpx" data-i="%d"%s><span>%s</span>%s</div>',
                implode(' ', $klassen), $i * self::TAG_PX, $i,
                $titel !== '' ? ' title="' . esc_attr($titel) . '"' : '',
                self::WOCHENTAGE[(int)$t->format('N')], $t->format('j')
            );
            // Ohne Zeitraum-Liste: Bänder aus zusammenhängenden Tagen gleichen Namens
            if (!$mitListe) {
                $f = $this->ferien[$t->format('d.m.y')] ?? null;
                if ($f !== $laufFerien) {
                    if ($laufFerien !== null) $ferienBaender .= $this->ferienBand($laufFerien, $ferienStart, $i);
                    $laufFerien = $f; $ferienStart = $i;
                }
            }
        }
        $monate .= $this->monatsBlock((string)$laufMonat, $monatStart, $n);
        if (!$mitListe && $laufFerien !== null) $ferienBaender .= $this->ferienBand($laufFerien, $ferienStart, $n);

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

    /**
     * Ein Band je Ferien-Zeitraum (Feiertage innerhalb der Ferien zerteilen es nicht).
     * Feiertage bekommen kein Band, sondern eine Markierung am Tag.
     */
    private function ferienBaenderAusListe(DateTime $erster, int $anzahlTage): string
    {
        $html = '';
        foreach ($this->ferienListe as $f) {
            if (!empty($f['feiertag'])) continue;
            $von = $this->tagIndex($erster, $f['start']);
            $bis = $this->tagIndex($erster, $f['ende']) + 1;
            if ($bis <= 0 || $von >= $anzahlTage) continue;          // außerhalb des Plans
            $html .= $this->ferienBand((string)$f['name'], max(0, $von), min($anzahlTage, $bis));
        }
        return $html;
    }

    /** @return array<string,string> Y-m-d → Name des Feiertags */
    private function feiertagsMap(): array
    {
        $map = [];
        foreach ($this->ferienListe as $f) {
            if (empty($f['feiertag'])) continue;
            for ($d = new DateTime($f['start']); $d <= new DateTime($f['ende']); $d->modify('+1 day')) {
                $map[$d->format('Y-m-d')] = (string)$f['name'];
            }
        }
        return $map;
    }

    private function tagIndex(DateTime $erster, string $datum): int
    {
        $d = new DateTime($datum);
        $tage = (int)$erster->diff($d)->days;
        return $d < $erster ? -$tage : $tage;
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

        // Je Spur: Ende des letzten Balkens, Ende seines Textes, Textanfang, Index im Ergebnis
        $spuren = [];
        $ergebnis = [];
        foreach ($termine as $t) {
            $links = (int)$start->diff($t->getBeginn())->days * self::TAG_PX;
            $tage = max(1, $t->getDauer());
            $breite = $tage * self::TAG_PX;
            $stichtag = $tage === 1;

            [$haupt, $neben] = $this->beschriftung($t);
            $textPx = $this->textBreite($haupt, $neben);
            // Text innen, wenn er passt – bei breiten Balken auch gekürzt innen (Tooltip zeigt alles)
            $innen = !$stichtag && ($textPx + 16 <= $breite || $breite >= 90);
            $textStart = $links + $breite + 6;
            $belegtBis = $innen ? $links + $breite + 4 : $textStart + $textPx + 10;

            // Passende Spur suchen: entweder frei, oder der Text des Vorgängers lässt
            // sich so kürzen, dass noch genug davon lesbar bleibt.
            $gewaehlt = null;
            foreach ($spuren as $nr => $s) {
                if ($s['belegtBis'] <= $links) { $gewaehlt = $nr; break; }
                if ($s['textStart'] !== null && $s['balkenEnde'] + 4 <= $links && $links - 8 - $s['textStart'] >= self::MIN_TEXT_PX) {
                    $ergebnis[$s['idx']]['textMax'] = $links - 8 - $s['textStart'];
                    $gewaehlt = $nr;
                    break;
                }
            }
            if ($gewaehlt === null) $gewaehlt = count($spuren);

            $ergebnis[] = [
                't' => $t, 'links' => $links, 'breite' => $breite, 'stichtag' => $stichtag,
                'haupt' => $haupt, 'neben' => $neben, 'innen' => $innen, 'spur' => $gewaehlt, 'textMax' => null,
            ];
            $spuren[$gewaehlt] = [
                'belegtBis'  => $belegtBis,
                'balkenEnde' => $links + $breite,
                'textStart'  => $innen ? null : $textStart,
                'idx'        => count($ergebnis) - 1,
            ];
        }
        return [$ergebnis, count($spuren)];
    }

    /** @return array{0:string,1:string} [ausgeschriebener Typ, Bezeichnung (falls zusätzlich informativ)] */
    private function beschriftung(Termin $t): array
    {
        $typ = $t->getEreignistyp();
        $haupt = rtrim(trim($this->typNamen[$typ] ?? '') ?: $typ, '. ');
        $bez = rtrim(trim($t->getBezeichnung()), '. ');
        // Bezeichnung nur zeigen, wenn sie mehr sagt als der Typ – auch nicht,
        // wenn eins im anderen steckt („Zeugniskonferenz“ vs. „Zeugniskonferenzen“)
        $n = fn(string $s) => mb_strtolower(trim($s));
        $doppelt = $bez === ''
            || $n($bez) === $n($typ)
            || mb_strpos($n($bez), $n($haupt)) !== false
            || mb_strpos($n($haupt), $n($bez)) !== false;
        return [$haupt, $doppelt ? '' : $bez];
    }

    /** Geschätzte Textbreite: fetter Haupttext (12px, 600) + normaler Zusatz */
    private function textBreite(string $haupt, string $neben = ''): int
    {
        $px = mb_strlen($haupt) * 7.3;
        if ($neben !== '') $px += (mb_strlen($neben) + 3) * 6.3;
        return (int)ceil($px);
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
            '<div class="%s" style="left:%dpx; width:%dpx; top:%dpx; --tt-farbe:%s; --tt-textfarbe:%s" title="%s" aria-label="%s" data-termin-id="%d" data-tag-von="%d" data-tage="%d"%s>'
            . '%s<span class="tt-zp-text"%s>%s</span></div>',
            implode(' ', $klassen),
            $p['links'],
            $p['breite'],
            $p['spur'] * self::SPUR_PX + 4,
            esc_attr($farbe),
            esc_attr($textFarbe),
            esc_attr($tooltip),
            esc_attr(str_replace("\n", ', ', $tooltip)),
            (int)$t->getId(),
            // Für die Hervorhebung der Tage im Kopf beim Darüberfahren
            intdiv($p['links'], self::TAG_PX),
            intdiv($p['breite'], self::TAG_PX),
            $editierbar ? sprintf(' tabindex="0" role="button" data-beginn="%s" data-ende="%s"', $t->getBeginn()->format('Y-m-d'), $t->getEnde()->format('Y-m-d')) : '',
            // Stichtag: Kachel genau eine Tagesspalte breit, mit der Tageszahl darin
            $p['stichtag'] ? '<span class="tt-zp-kachel" aria-hidden="true">' . $t->getBeginn()->format('j') . '</span>' : '',
            $p['textMax'] !== null ? ' style="max-width:' . (int)$p['textMax'] . 'px"' : '',
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
