<?php
declare(strict_types=1);

namespace MH\Timetable\Model\Repository;

use MH\Timetable\Model\Entity\Ferien;
use wpdb;
use DateTime;

class WpdbFerienRepository implements FerienRepositoryInterface
{
    private wpdb $wpdb;
    private string $tableName;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->tableName = $this->wpdb->prefix . 'tt_ferien';
    }

    public function exists(Ferien $ferien): bool
    {
        $count = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->tableName} WHERE name = %s AND startdatum = %s AND enddatum = %s AND typ = %s",
            $ferien->getName(),
            $ferien->getStartdatum()->format('Y-m-d'),
            $ferien->getEnddatum()->format('Y-m-d'),
            $ferien->getTyp()
        ));

        return (int)$count > 0;
    }

    public function create(Ferien $ferien): bool
    {
        if ($this->exists($ferien)) {
            return false;
        }

        return false !== $this->wpdb->insert(
            $this->tableName,
            [
                'name'       => $ferien->getName(),
                'startdatum' => $ferien->getStartdatum()->format('Y-m-d'),
                'enddatum'   => $ferien->getEnddatum()->format('Y-m-d'),
                'typ'        => $ferien->getTyp(),
            ],
            ['%s', '%s', '%s', '%s']
        );
    }

    public function getAll(): array
    {
        $results = $this->wpdb->get_results("SELECT * FROM {$this->tableName}", ARRAY_A);
        return array_map([$this, 'mapToEntity'], $results ?: []);
    }

    public function find(int $id): ?Ferien
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->tableName} WHERE id = %d", $id),
            ARRAY_A
        );
        return $row ? $this->mapToEntity($row) : null;
    }

    public function delete(int $id): bool
    {
        return false !== $this->wpdb->delete($this->tableName, ['id' => $id], ['%d']);
    }

    private function mapToEntity(array $row): Ferien
    {
        $ferien = new Ferien(
            $row['name'],
            new DateTime($row['startdatum']),
            new DateTime($row['enddatum']),
            $row['typ']
        );
        $ferien->setId((int)$row['id']);
        return $ferien;
    }
}