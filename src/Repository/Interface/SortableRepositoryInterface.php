<?php

namespace App\Repository\Interface;

use App\Entity\Club;
use App\Entity\Interface\SortableEntityInterface;

interface SortableRepositoryInterface {
  public function getLatestAvailableWeight(Club $club): int;
  public function getUpperItem(Club $club, int $currentWeight): ?SortableEntityInterface;
  public function getLowerItem(Club $club, int $currentWeight): ?SortableEntityInterface;

  public function moveUp(Club $club, SortableEntityInterface $itemToMove): void;
  public function moveDown(Club $club, SortableEntityInterface $itemToMove): void;
  public function reorder(Club $club, array $uuids): void;
}
