<?php

namespace App\Controller\ClubDependent\Plugin\Presence;

use App\Controller\Abstract\AbstractClubDependentController;
use App\Repository\ClubDependent\Plugin\Presence\ExternalPresenceRepository;
use Symfony\Component\HttpFoundation\Request;

class ExternalPresenceToday extends AbstractClubDependentController {

  public function __invoke(Request $request, ExternalPresenceRepository $externalPresenceRepository): ?array {
    return $externalPresenceRepository->findAllPresentToday($this->getClub($request));
  }

}
