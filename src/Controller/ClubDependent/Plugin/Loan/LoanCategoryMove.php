<?php

namespace App\Controller\ClubDependent\Plugin\Loan;

use App\Controller\Abstract\SortableController;
use App\Entity\ClubDependent\Plugin\Loan\LoanCategory;
use App\Repository\ClubDependent\Plugin\Loan\LoanCategoryRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class LoanCategoryMove extends SortableController {

  public function __invoke(Request $request, #[MapEntity(mapping: ['uuid' => 'uuid'])] LoanCategory $loanCategory, LoanCategoryRepository $loanCategoryRepository): JsonResponse {
    return $this->move($request, $loanCategory, $loanCategoryRepository);
  }

}
