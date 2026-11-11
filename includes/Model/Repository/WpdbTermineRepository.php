<?php
declare(strict_types=1);

namespace MH\Timetable\Model\Repository;

use MH\Timetable\Model\Entity\Termin;
use wpdb;
use DateTime;

/**
 * Termine-Repository.
 *
 * Bildungsgang und Ereignistyp werden als Term-ID gespeichert (*_id). Beim Lesen
 * wird der aktuelle Term-Name per JOIN geholt – Umbenennungen wirken also sofort.
 * Die Namensspalten bleiben als Fallback erhalten, falls ein Term gelöscht wurde.
 */
class WpdbTermineRepository implements TermineRepositoryInterface
{
    private wpdb $wpdb;
    private string $tableName;

    /** @var array<string, ?int> Cache für Name→Term-ID je Taxonomie */
    private array $termIdCache = [];

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->tableName = $this->wpdb->prefix . 'tt_termine';
    }

    /** SELECT mit aufgelösten Term-Namen. */
    private function baseSelect(): string
    {
        $terms = $this->wpdb->terms;
        return "SELECT t.*,
                       COALESCE(bg.name, t.bildungsgang) AS bildungsgang_name,
                       COALESCE(et.name, t.ereignistyp)  AS ereignistyp_name
                  FROM {$this->tableName} t
             LEFT JOIN {$terms} bg ON bg.term_id = t.bildungsgang_id
             LEFT JOIN {$terms} et ON et.term_id = t.ereignistyp_id";
    }

    public function find(int $id): ?Termin
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare($this->baseSelect() . " WHERE t.id = %d", $id),
            ARRAY_A
        );

        return $row ? $this->mapToEntity($row) : null;
    }

    public function getAll(): array
    {
        $results = $this->wpdb->get_results($this->baseSelect(), ARRAY_A);
        return array_map([$this, 'mapToEntity'], $results ?: []);
    }

    public function create(Termin $termin): bool
    {
        $result = $this->wpdb->insert($this->tableName, $this->toRow($termin), $this->rowFormats());
        if ($result) {
            $termin->setId((int)$this->wpdb->insert_id);
        }
        return false !== $result;
    }

    public function update(int $id, Termin $termin): bool
    {
        return false !== $this->wpdb->update(
            $this->tableName,
            $this->toRow($termin),
            ['id' => $id],
            $this->rowFormats(),
            ['%d']
        );
    }

    public function delete(int $id): bool
    {
        return false !== $this->wpdb->delete($this->tableName, ['id' => $id], ['%d']);
    }

    public function getByTimetableId(int $timetableId): array
    {
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                $this->baseSelect() . " WHERE t.timetable_ID = %d ORDER BY bildungsgang_name, t.beginn",
                $timetableId
            ),
            ARRAY_A
        );

        return array_map([$this, 'mapToEntity'], $results ?: []);
    }

    public function countByTimetableId(int $timetableId): int
    {
        return (int)$this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->tableName} WHERE timetable_ID = %d",
                $timetableId
            )
        );
    }

    public function getFiltered(int $timetableId, string $bildungsgang, string $ereignistyp): array
    {
        $sql = $this->baseSelect() . " WHERE 1=1";
        $params = [];

        if ($bildungsgang !== '') {
            $sql .= " AND COALESCE(bg.name, t.bildungsgang) = %s";
            $params[] = $bildungsgang;
        }

        if ($ereignistyp !== '') {
            $sql .= " AND COALESCE(et.name, t.ereignistyp) = %s";
            $params[] = $ereignistyp;
        }

        if ($timetableId > 0) {
            $sql .= " AND t.timetable_ID = %d";
            $params[] = $timetableId;
        }

        // Nur prepare aufrufen, wenn wir auch Parameter haben
        $query = !empty($params) ? $this->wpdb->prepare($sql, ...$params) : $sql;
        $results = $this->wpdb->get_results($query, ARRAY_A);

        return array_map([$this, 'mapToEntity'], $results ?: []);
    }

    /** Entity → DB-Zeile (inkl. aufgelöster Term-IDs). */
    private function toRow(Termin $termin): array
    {
        return [
            'bildungsgang'    => $termin->getBildungsgang(),
            'bildungsgang_id' => $this->resolveTermId($termin->getBildungsgang(), 'bildungsgang'),
            'bezeichnung'     => $termin->getBezeichnung(),
            'ereignistyp'     => $termin->getEreignistyp(),
            'ereignistyp_id'  => $this->resolveTermId($termin->getEreignistyp(), 'ereignistyp'),
            'beginn'          => $termin->getBeginn()->format('Y-m-d'),
            'ende'            => $termin->getEnde()->format('Y-m-d'),
            'verantwortlich'  => $termin->getVerantwortlich(),
            'timetable_ID'    => $termin->getTimetableId(),
        ];
    }

    private function rowFormats(): array
    {
        // wpdb schreibt NULL-Werte unabhängig vom Format korrekt als NULL.
        return ['%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%d'];
    }

    /**
     * Sucht die Term-ID zu einem Namen. Legt bewusst KEINEN Term an –
     * gelöschte Terms sollen nicht durch Kopieren alter Termine wiederkehren.
     */
    private function resolveTermId(string $name, string $taxonomy): ?int
    {
        if ($name === '') return null;

        $key = $taxonomy . '|' . $name;
        if (!array_key_exists($key, $this->termIdCache)) {
            $term = get_term_by('name', $name, $taxonomy);
            $this->termIdCache[$key] = ($term && !is_wp_error($term)) ? (int)$term->term_id : null;
        }
        return $this->termIdCache[$key];
    }

    /**
     * Hilfsmethode: Wandelt ein Datenbank-Array in ein Termin-Objekt um.
     */
    private function mapToEntity(array $row): Termin
    {
        $termin = new Termin(
            new DateTime($row['beginn']),
            new DateTime($row['ende']),
            (string)($row['bildungsgang_name'] ?? $row['bildungsgang']),
            (string)$row['bezeichnung'],
            (string)($row['ereignistyp_name'] ?? $row['ereignistyp']),
            (string)$row['verantwortlich'],
            (int)$row['timetable_ID']
        );
        $termin->setId((int)$row['id']);
        return $termin;
    }
}
