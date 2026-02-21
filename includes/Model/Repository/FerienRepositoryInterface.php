<?php
declare(strict_types=1);

namespace MH\Timetable\Model\Repository;

use MH\Timetable\Model\Entity\Ferien;

interface FerienRepositoryInterface
{
    public function find(int $id): ?Ferien;
    public function getAll(): array;
    public function create(Ferien $ferien): bool;
    public function delete(int $id): bool;
    public function exists(Ferien $ferien): bool;
}