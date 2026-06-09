<?php
declare(strict_types=1);

require_once __DIR__ . '/../Repositories/ShiftRepository.php';

class ShiftService
{
    private ShiftRepository $repo;

    public function __construct(?ShiftRepository $repo = null)
    {
        $this->repo = $repo ?? new ShiftRepository();
    }

    public function listOpen(?int $storeId): array
    {
        return $this->repo->listOpen($storeId);
    }
}
