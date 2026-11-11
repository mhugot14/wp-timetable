<?php
declare(strict_types=1);

namespace MH\Timetable\Model\Entity;

use DateTime;

class Ferien
{
    private ?int $id = null;
    private string $name;
    private DateTime $startdatum;
    private DateTime $enddatum;
    private string $typ;

    public function __construct(
        string $name,
        DateTime $startdatum,
        DateTime $enddatum,
        string $typ
    ) {
        $this->name = $name;
        $this->startdatum = $startdatum;
        $this->enddatum = $enddatum;
        $this->typ = $typ;
    }

    // Getter & Setter
    public function getId(): ?int { return $this->id; }
    public function setId(int $id): void { $this->id = $id; }
    public function getName(): string { return $this->name; }
    public function getStartdatum(): DateTime { return $this->startdatum; }
    public function getEnddatum(): DateTime { return $this->enddatum; }
    public function getTyp(): string { return $this->typ; }
    public function istFeiertag(): bool { return strcasecmp($this->typ, 'Feiertag') === 0; }

    /** Lesbarer Name, z. B. „Weihnachtsferien“ statt „weihnachtsferien nordrhein-westfalen 2025“. */
    public function getAnzeigename(): string { return self::anzeigeName($this->name); }

    /**
     * Bereinigt Namen aus der Ferien-API: Jahreszahl und Bundesland am Ende
     * entfernen, ersten Buchstaben groß schreiben.
     */
    public static function anzeigeName(string $name): string
    {
        $laender = ['baden-württemberg', 'bayern', 'berlin', 'brandenburg', 'bremen', 'hamburg', 'hessen',
            'mecklenburg-vorpommern', 'niedersachsen', 'nordrhein-westfalen', 'rheinland-pfalz', 'saarland',
            'sachsen-anhalt', 'sachsen', 'schleswig-holstein', 'thüringen'];
        $n = trim(preg_replace('/\s+\d{4}(\/\d{2,4})?$/u', '', trim($name)));
        foreach ($laender as $land) {
            if (mb_strlen($n) > mb_strlen($land) && mb_strtolower(mb_substr($n, -mb_strlen($land))) === $land) {
                $n = trim(mb_substr($n, 0, -mb_strlen($land)));
                break;
            }
        }
        if ($n === '') return trim($name);
        return mb_strtoupper(mb_substr($n, 0, 1)) . mb_substr($n, 1);
    }
}