<?php
declare(strict_types=1);

namespace MH\Timetable\Model\Repository;

use MH\Timetable\Model\Entity\Termin;
use wpdb;

class WpdbTermineRepository implements TermineRepositoryInterface
{
    private wpdb $wpdb;
    private string $tableName;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->tableName = $this->wpdb->prefix . 'tt_termine';
    }

    public function find(int $id): ?Termin
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->tableName} WHERE id = %d", $id),
            ARRAY_A
        );

        return $row ? $this->mapToEntity($row) : null;
    }

    public function getAll(): array
    {
        $results = $this->wpdb->get_results("SELECT * FROM {$this->tableName}", ARRAY_A);
        return array_map([$this, 'mapToEntity'], $results ?: []);
    }

    public function create(Termin $termin): bool
    {
        return false !== $this->wpdb->insert(
            $this->tableName,
            [
                'bildungsgang'   => $termin->getBildungsgang(),
                'bezeichnung'    => $termin->getBezeichnung(),
                'ereignistyp'    => $termin->getEreignistyp(),
                'beginn'         => $termin->getBeginn()->format('Y-m-d'),
                'ende'           => $termin->getEnde()->format('Y-m-d'),
                'verantwortlich' => $termin->getVerantwortlich(),
                'timetable_ID'   => $termin->getTimetableId(),
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%d']
        );
    }

    public function update(int $id, Termin $termin): bool
    {
        return false !== $this->wpdb->update(
            $this->tableName,
            [
                'bildungsgang'   => $termin->getBildungsgang(),
                'bezeichnung'    => $termin->getBezeichnung(),
                'ereignistyp'    => $termin->getEreignistyp(),
                'beginn'         => $termin->getBeginn()->format('Y-m-d'),
                'ende'           => $termin->getEnde()->format('Y-m-d'),
                'verantwortlich' => $termin->getVerantwortlich(),
                'timetable_ID'   => $termin->getTimetableId(),
            ],
            ['id' => $id],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%d'],
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
                "SELECT * FROM {$this->tableName} WHERE timetable_ID = %d ORDER BY bildungsgang, beginn",
                $timetableId
            ),
            ARRAY_A
        );

        return array_map([$this, 'mapToEntity'], $results ?: []);
    }

    public function getFiltered(int $timetableId, string $bildungsgang, string $ereignistyp): array
	{
		$sql = "SELECT * FROM {$this->tableName} WHERE 1=1";
		$params = [];

		if (!empty($bildungsgang)) {
			$sql .= " AND bildungsgang = %s";
			$params[] = $bildungsgang;
		}

		if (!empty($ereignistyp)) {
			$sql .= " AND ereignistyp = %s";
			$params[] = $ereignistyp; // FIX: Dieser Wert wurde vorher nicht hinzugefügt!
		}

		if ($timetableId > 0) {
			$sql .= " AND timetable_ID = %d";
			$params[] = $timetableId;
		}

		// Nur prepare aufrufen, wenn wir auch Parameter haben
		$query = !empty($params) ? $this->wpdb->prepare($sql, ...$params) : $sql;
		$results = $this->wpdb->get_results($query, ARRAY_A);

		return array_map([$this, 'mapToEntity'], $results ?: []);
	}

    /**
     * Hilfsmethode: Wandelt ein Datenbank-Array in ein Termin-Objekt um.
     */
    private function mapToEntity(array $row): Termin
    {
        $termin = new Termin(
            new \DateTime($row['beginn']),
            new \DateTime($row['ende']),
            $row['bildungsgang'],
            $row['bezeichnung'],
            $row['ereignistyp'],
            $row['verantwortlich'],
            (int)$row['timetable_ID']
        );
        $termin->setId((int)$row['id']);
        return $termin;
    }
}