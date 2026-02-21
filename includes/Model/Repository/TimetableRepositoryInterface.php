<?php
declare(strict_types=1);

namespace MH\Timetable\Model\Repository;

use MH\Timetable\Model\Entity\Timetable;

interface TimetableRepositoryInterface
{
    public function find(int $id): ?Timetable;

    /** @return Timetable[] */
    public function getAll(): array;

    public function create(Timetable $timetable): bool;

    public function update(int $id, Timetable $timetable): bool;

    public function delete(int $id): bool;
}