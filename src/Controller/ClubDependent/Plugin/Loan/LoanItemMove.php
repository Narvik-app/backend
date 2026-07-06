<?php

namespace App\Controller\ClubDependent\Plugin\Loan;

use App\Controller\Abstract\SortableController;
use App\Entity\ClubDependent\Plugin\Loan\LoanItem;
use App\Repository\ClubDependent\Plugin\Loan\LoanItemRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class LoanItemMove extends SortableController {

  public function __invoke(Request $request, #[MapEntity(mapping: ['uuid' => 'uuid'])] LoanItem $loanItem, LoanItemRepository $loanItemRepository): JsonResponse {
    return $this->move($request, $loanItem, $loanItemRepository);
  }

}
