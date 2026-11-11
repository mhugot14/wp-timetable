<?php
declare(strict_types=1);

namespace MH\Timetable\Service;

use MH\Timetable\Model\Entity\Termin;
use MH\Timetable\Model\Repository\TermineRepositoryInterface;
use MH\Timetable\Model\Repository\WpdbEreignistypRepository;
use DateTime;

/**
 * Abhängige Fristen zwischen Ereignistypen, z. B.
 *   „NE endet spätestens 3 Schultage vor Beginn der ZK“  (Richtung „vor“)
 *   „ZA beginnt frühestens 1 Schultag nach Ende der ZK“  (Richtung „nach“)
 *
 * Eine Regel gilt jeweils innerhalb derselben Timetable und desselben
 * Bildungsgangs. Wird der Bezugstermin (ZK) verschoben, wandern die abhängigen
 * Termine (NE, ZA) mit: Ihr Abstand in Schultagen bleibt erhalten, unterschreitet
 * aber nie den Mindestabstand der Regel. Das setzt sich über Regelketten fort.
 *
 * Die Regeln liegen als Option mit Ereignistyp-IDs – Umbenennungen sind egal.
 */
class FristenService
{
    public const OPTION = 'mh_tt_fristen';
    private const MAX_KETTE = 5;

    private SchulkalenderService $kalender;
    private TermineRepositoryInterface $termineRepo;
    private WpdbEreignistypRepository $ereignistypen;

    public function __construct(SchulkalenderService $kalender, TermineRepositoryInterface $termineRepo, WpdbEreignistypRepository $ereignistypen)
    {
        $this->kalender = $kalender;
        $this->termineRepo = $termineRepo;
        $this->ereignistypen = $ereignistypen;
    }

    /**
     * Nach dem Zusammenführen zweier Ereignistypen: Regeln von $quelleId auf $zielId
     * umstellen. Regeln, die dadurch sinnlos (Typ hängt von sich selbst ab) oder
     * doppelt würden, entfallen.
     * @return string[] Meldungen über entfallene Regeln
     */
    public function ersetzeEreignistyp(int $quelleId, int $zielId): array
    {
        $meldungen = [];
        $neu = [];
        $schluessel = [];
        foreach ((array)get_option(self::OPTION, []) as $regel) {
            foreach (['abhaengig_id', 'bezug_id'] as $feld) {
                if ((int)$regel[$feld] === $quelleId) $regel[$feld] = $zielId;
            }
            $key = $regel['abhaengig_id'] . '>' . $regel['bezug_id'];
            $umgekehrt = $regel['bezug_id'] . '>' . $regel['abhaengig_id'];
            if ((int)$regel['abhaengig_id'] === (int)$regel['bezug_id'] || isset($schluessel[$key]) || isset($schluessel[$umgekehrt])) {
                $meldungen[] = 'Eine Fristen-Regel entfällt, weil sie nach dem Zusammenführen doppelt oder widersprüchlich wäre.';
                continue;
            }
            $schluessel[$key] = true;
            $neu[] = $regel;
        }
        update_option(self::OPTION, $neu, false);
        return $meldungen;
    }

    // --- Regelverwaltung -------------------------------------------------

    /**
     * Alle Regeln mit aufgelösten Typ-Namen. Regeln, deren Ereignistyp
     * gelöscht wurde, werden ausgelassen.
     * @return array<int, array{id:string, abhaengig_id:int, bezug_id:int, richtung:string, abstand:int, abhaengig:string, bezug:string}>
     */
    public function getRegeln(): array
    {
        $regeln = [];
        foreach ((array)get_option(self::OPTION, []) as $regel) {
            $a = $this->ereignistypen->find((int)($regel['abhaengig_id'] ?? 0));
            $b = $this->ereignistypen->find((int)($regel['bezug_id'] ?? 0));
            if (!$a || !$b) {
                continue;
            }
            $regel['abhaengig'] = $a->getKuerzel();
            $regel['bezug'] = $b->getKuerzel();
            $regeln[] = $regel;
        }
        return $regeln;
    }

    /** @return string|null Fehlermeldung oder null bei Erfolg */
    public function addRegel(int $abhaengigId, int $bezugId, string $richtung, int $abstand): ?string
    {
        if ($abhaengigId <= 0 || $bezugId <= 0) return 'Bitte beide Ereignistypen auswählen.';
        if ($abhaengigId === $bezugId) return 'Ein Ereignistyp kann nicht von sich selbst abhängen.';
        if (!in_array($richtung, ['vor', 'nach'], true)) return 'Ungültige Richtung.';
        if ($abstand < 0 || $abstand > 60) return 'Der Abstand muss zwischen 0 und 60 Schultagen liegen.';

        $regeln = (array)get_option(self::OPTION, []);
        foreach ($regeln as $r) {
            if ((int)$r['abhaengig_id'] === $abhaengigId && (int)$r['bezug_id'] === $bezugId) {
                return 'Für diese beiden Ereignistypen gibt es bereits eine Regel.';
            }
            if ((int)$r['abhaengig_id'] === $bezugId && (int)$r['bezug_id'] === $abhaengigId) {
                return 'Es gibt bereits die umgekehrte Regel – zwei Typen können nicht gegenseitig voneinander abhängen.';
            }
        }

        $regeln[] = [
            'id'           => wp_generate_uuid4(),
            'abhaengig_id' => $abhaengigId,
            'bezug_id'     => $bezugId,
            'richtung'     => $richtung,
            'abstand'      => $abstand,
        ];
        update_option(self::OPTION, $regeln, false);
        return null;
    }

    /** Entfernt alle Regeln, die einen (gelöschten) Ereignistyp verwenden. @return int Anzahl */
    public function entferneEreignistyp(int $ereignistypId): int
    {
        $regeln = (array)get_option(self::OPTION, []);
        $neu = array_values(array_filter($regeln, fn($r) => (int)$r['abhaengig_id'] !== $ereignistypId && (int)$r['bezug_id'] !== $ereignistypId));
        update_option(self::OPTION, $neu, false);
        return count($regeln) - count($neu);
    }

    public function deleteRegel(string $id): bool
    {
        $regeln = (array)get_option(self::OPTION, []);
        $neu = array_values(array_filter($regeln, fn($r) => ($r['id'] ?? '') !== $id));
        if (count($neu) === count($regeln)) return false;
        update_option(self::OPTION, $neu, false);
        return true;
    }

    public function beschreibe(array $regel): string
    {
        if ((int)$regel['abstand'] === 0) {
            return $regel['richtung'] === 'vor'
                ? sprintf('%s endet spätestens am Tag, an dem %s beginnt', $regel['abhaengig'], $regel['bezug'])
                : sprintf('%s beginnt frühestens am Tag, an dem %s endet', $regel['abhaengig'], $regel['bezug']);
        }
        $tage = (int)$regel['abstand'] === 1 ? '1 Schultag' : $regel['abstand'] . ' Schultage';
        return $regel['richtung'] === 'vor'
            ? sprintf('%s endet spätestens %s vor Beginn von %s', $regel['abhaengig'], $tage, $regel['bezug'])
            : sprintf('%s beginnt frühestens %s nach Ende von %s', $regel['abhaengig'], $tage, $regel['bezug']);
    }

    // --- Prüfen ----------------------------------------------------------

    /**
     * Maßgeblicher Bezugstermin einer Regel in einem Bildungsgang.
     * Eine Timetable ist ein Durchgang (z. B. Zeugnisse Winter), daher gilt:
     *  - „vor“:  der FRÜHESTE Bezugstermin (NE muss vor der ersten ZK fertig sein)
     *  - „nach“: der SPÄTESTE Bezugstermin (ZA erst nach der letzten ZK)
     * Mehrere ZK am selben Tag (je Klasse) sind damit unproblematisch.
     *
     * @param Termin[] $alle Termine derselben Timetable
     */
    private function bindenderBezug(array $regel, string $bildungsgang, array $alle): ?Termin
    {
        $treffer = null;
        foreach ($alle as $t) {
            if ($t->getBildungsgang() !== $bildungsgang || $t->getEreignistyp() !== $regel['bezug']) {
                continue;
            }
            if ($treffer === null
                || ($regel['richtung'] === 'vor' && $t->getBeginn() < $treffer->getBeginn())
                || ($regel['richtung'] === 'nach' && $t->getEnde() > $treffer->getEnde())) {
                $treffer = $t;
            }
        }
        return $treffer;
    }

    /** Spätestes Ende („vor“) bzw. frühester Beginn („nach“) laut Regel. */
    private function grenze(array $regel, Termin $bezug): DateTime
    {
        return $regel['richtung'] === 'vor'
            ? $this->kalender->verschiebeUmSchultage($bezug->getBeginn(), -(int)$regel['abstand'])
            : $this->kalender->verschiebeUmSchultage($bezug->getEnde(), (int)$regel['abstand']);
    }

    /**
     * Fristverstöße eines Termins (als abhängiger Termin betrachtet).
     * @param Termin[] $alle Termine derselben Timetable
     * @return string[]
     */
    public function pruefe(Termin $termin, array $alle): array
    {
        $meldungen = [];
        foreach ($this->getRegeln() as $regel) {
            if ($termin->getEreignistyp() !== $regel['abhaengig']) continue;

            $bezug = $this->bindenderBezug($regel, $termin->getBildungsgang(), $alle);
            if (!$bezug || $bezug->getId() === $termin->getId()) continue;

            $grenze = $this->grenze($regel, $bezug);
            if ($regel['richtung'] === 'vor' && $termin->getEnde() > $grenze) {
                $meldungen[] = sprintf(
                    'Frist verletzt: %s – endet am %s, sollte spätestens am %s enden (%s am %s).',
                    $this->beschreibe($regel), $termin->getEnde()->format('d.m.Y'), $grenze->format('d.m.Y'),
                    $regel['bezug'], $bezug->getBeginn()->format('d.m.Y')
                );
            } elseif ($regel['richtung'] === 'nach' && $termin->getBeginn() < $grenze) {
                $meldungen[] = sprintf(
                    'Frist verletzt: %s – beginnt am %s, sollte frühestens am %s beginnen (%s endet am %s).',
                    $this->beschreibe($regel), $termin->getBeginn()->format('d.m.Y'), $grenze->format('d.m.Y'),
                    $regel['bezug'], $bezug->getEnde()->format('d.m.Y')
                );
            }
        }
        return $meldungen;
    }

    // --- Mitziehen -------------------------------------------------------

    /**
     * Zieht abhängige Termine mit, nachdem $neu (vorher $alt) gespeichert wurde.
     * @return string[] Beschreibung jeder Verschiebung
     */
    public function anwenden(Termin $neu, Termin $alt, int $tiefe = 0): array
    {
        if ($tiefe >= self::MAX_KETTE || !$neu->getId()) return [];

        $beginnGeaendert = $neu->getBeginn() != $alt->getBeginn();
        $endeGeaendert   = $neu->getEnde() != $alt->getEnde();
        if (!$beginnGeaendert && !$endeGeaendert) return [];

        // Zustand vorher (mit alter Lage des Termins) und nachher
        $alleNeu = $this->termineRepo->getByTimetableId($neu->getTimetableId());
        $alleAlt = [];
        foreach ($alleNeu as $t) {
            $alleAlt[] = $t->getId() === $neu->getId() ? $alt : $t;
        }

        $meldungen = [];
        foreach ($this->getRegeln() as $regel) {
            if ($neu->getEreignistyp() !== $regel['bezug'] && $alt->getEreignistyp() !== $regel['bezug']) continue;

            foreach (array_unique([$alt->getBildungsgang(), $neu->getBildungsgang()]) as $bg) {
                $bezugAlt = $this->bindenderBezug($regel, $bg, $alleAlt);
                $bezugNeu = $this->bindenderBezug($regel, $bg, $alleNeu);
                if (!$bezugAlt || !$bezugNeu) continue;

                // Hat sich das maßgebliche Datum überhaupt geändert?
                $datumAlt = $regel['richtung'] === 'vor' ? $bezugAlt->getBeginn() : $bezugAlt->getEnde();
                $datumNeu = $regel['richtung'] === 'vor' ? $bezugNeu->getBeginn() : $bezugNeu->getEnde();
                if ($datumAlt == $datumNeu) continue;

                foreach ($alleNeu as $abh) {
                    if ($abh->getEreignistyp() !== $regel['abhaengig'] || $abh->getBildungsgang() !== $bg) continue;

                    // Bisherigen Abstand in Schultagen halten, aber nie unter den Mindestabstand
                    $vorher = clone $abh;
                    $vorher->setId((int)$abh->getId()); // clone setzt die ID zurück
                    if ($regel['richtung'] === 'vor') {
                        $abstand = max((int)$regel['abstand'], $this->kalender->schultageZwischen($abh->getEnde(), $datumAlt));
                        $ziel = $this->kalender->verschiebeUmSchultage($datumNeu, -$abstand);
                        $tage = $this->tageDifferenz($abh->getEnde(), $ziel);
                    } else {
                        $abstand = max((int)$regel['abstand'], $this->kalender->schultageZwischen($datumAlt, $abh->getBeginn()));
                        $ziel = $this->kalender->verschiebeUmSchultage($datumNeu, $abstand);
                        $tage = $this->tageDifferenz($abh->getBeginn(), $ziel);
                    }
                    if ($tage === 0) continue;

                    $abh->setBeginn((clone $abh->getBeginn())->modify(sprintf('%+d days', $tage)));
                    $abh->setEnde((clone $abh->getEnde())->modify(sprintf('%+d days', $tage)));
                    if (!$this->termineRepo->update((int)$abh->getId(), $abh)) continue;

                    $meldungen[] = sprintf(
                        '%s %s „%s“ mitverschoben: %s–%s → %s–%s (%s)',
                        $abh->getBildungsgang(), $abh->getEreignistyp(), $abh->getBezeichnung(),
                        $vorher->getBeginn()->format('d.m.'), $vorher->getEnde()->format('d.m.Y'),
                        $abh->getBeginn()->format('d.m.'), $abh->getEnde()->format('d.m.Y'),
                        $this->beschreibe($regel)
                    );
                    // Kette: der verschobene Termin kann selbst Bezug weiterer Regeln sein
                    $meldungen = array_merge($meldungen, $this->anwenden($abh, $vorher, $tiefe + 1));
                }
            }
        }
        return $meldungen;
    }

    private function tageDifferenz(DateTime $von, DateTime $bis): int
    {
        $tage = (int)$von->diff($bis)->days;
        return $bis < $von ? -$tage : $tage;
    }
}
