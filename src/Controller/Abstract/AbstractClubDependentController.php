<?php

namespace App\Controller\Abstract;

use App\Entity\Club;
use App\Enum\ClubRole;
use App\Enum\UserRole;
use App\Service\RequestService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

abstract class AbstractClubDependentController extends AbstractController {

  public function __construct(
    private readonly RequestService $requestService,
  ) {
  }

  public function getClub(Request $request): Club {
    $club = $this->requestService->getClubFromRequest($request);
    if (!$club instanceof Club) {
      throw $this->createNotFoundException();
    }
    return $club;
  }
}
