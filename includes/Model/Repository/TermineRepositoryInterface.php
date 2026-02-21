<?php
declare(strict_types=1);

namespace MH\Timetable\Model\Repository;

use MH\Timetable\Model\Entity\Termin;

interface TermineRepositoryInterface
{
    public function find(int $id): ?Termin;
    public function getAll(): array;
    public function create(Termin $termin): bool;
    public function update(int $id, Termin $termin): bool;
    public function delete(int $id): bool;
    public function getByTimetableId(int $timetableId): array;
    public function getFiltered(int $timetableId, string $bildungsgang, string $ereignistyp): array;
}