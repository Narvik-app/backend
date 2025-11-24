<?php

namespace App\Controller\ClubDependent\Plugin\Sale;

use App\Controller\Abstract\SortableController;
use App\Repository\ClubDependent\Plugin\Sale\InventoryCategoryRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class InventoryCategoryReorder extends SortableController {

  public function __invoke(Request $request, InventoryCategoryRepository $inventoryCategoryRepository): JsonResponse {
    return $this->reorder($request, $inventoryCategoryRepository);
  }

}
