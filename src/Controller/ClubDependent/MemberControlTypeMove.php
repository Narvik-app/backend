<?php

namespace App\Controller\ClubDependent;

use App\Controller\Abstract\SortableController;
use App\Entity\ClubDependent\MemberControlType;
use App\Repository\ClubDependent\MemberControlTypeRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class MemberControlTypeMove extends SortableController {

  public function __invoke(Request $request, #[MapEntity(mapping: ['uuid' => 'uuid'])] MemberControlType $memberControlType, MemberControlTypeRepository $memberControlTypeRepository): JsonResponse {
    return $this->move($request, $memberControlType, $memberControlTypeRepository);
  }

}
