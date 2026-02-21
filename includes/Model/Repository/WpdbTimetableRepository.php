<?php
declare(strict_types=1);

namespace MH\Timetable\Model\Repository;

use MH\Timetable\Model\Entity\Timetable;
use wpdb;
use DateTime;

class WpdbTimetableRepository implements TimetableRepositoryInterface
{
    private wpdb $wpdb;
    private string $tableName;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->tableName = $this->wpdb->prefix . 'tt_timetable';
    }

    public function find(int $id): ?Timetable
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->tableName} WHERE id = %d", $id),
            ARRAY_A
        );

        return $row ? $this->mapToEntity($row) : null;
    }

    public function getAll(): array
    {
        $results = $this->wpdb->get_results(
            "SELECT * FROM {$this->tableName} ORDER BY id DESC",
            ARRAY_A
        );

        return array_map([$this, 'mapToEntity'], $results ?: []);
    }

    public function create(Timetable $timetable): bool
    {
         $result = $this->wpdb->insert(
			$this->tableName,
			[
				'bezeichnung'  => $timetable->getBezeichnung(),
				'beschreibung' => $timetable->getBeschreibung(),
				'erzeugt_am'   => $timetable->getErzeugtAm()->format('Y-m-d H:i:s')
			]
		);

		if ($result) {
			$timetable->setId((int)$this->wpdb->insert_id); // ID vom Server setzen!
		}

		return false !== $result;
    }

    public function update(int $id, Timetable $timetable): bool
    {
        $result = $this->wpdb->update(
            $this->tableName,
            [
                'bezeichnung'  => $timetable->getBezeichnung(),
                'beschreibung' => $timetable->getBeschreibung()
            ],
            ['id' => $id],
            ['%s', '%s'],
            ['%d']
        );

        return false !== $result;
    }

    public function delete(int $id): bool
    {
        return false !== $this->wpdb->delete($this->tableName, ['id' => $id], ['%d']);
    }

    /**
     * Hilfsmethode: Mapping von DB-Array zu Entity-Objekt.
     */
    private function mapToEntity(array $row): Timetable
    {
        $timetable = new Timetable();
        $timetable->setId((int)$row['id']);
        $timetable->setBezeichnung($row['bezeichnung']);
        $timetable->setBeschreibung($row['beschreibung']);
        
        $erzeugtAm = DateTime::createFromFormat('Y-m-d H:i:s', $row['erzeugt_am']);
        if ($erzeugtAm) {
            $timetable->setErzeugtAm($erzeugtAm);
        }

        return $timetable;
    }
}