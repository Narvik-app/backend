<?php

namespace App\Controller;

use App\Controller\Abstract\AbstractController;
use App\Entity\Club;
use App\Entity\UserSecurityCode;
use App\Enum\UserSecurityCodeTrigger;
use App\Service\ClubService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;

class ClubBadgerQuickLogin extends AbstractController {

  public function __construct(
    private readonly ClubService $clubService,
    private readonly EntityManagerInterface $em,
  ) {
  }

  public function __invoke(#[MapEntity(mapping: ['uuid' => 'uuid'])] Club $club): JsonResponse {
    $user = $this->clubService->getBadger($club);
    if (!$user) {
      throw $this->createNotFoundException();
    }

    $securityCode = new UserSecurityCode();
    $securityCode->setTrigger(UserSecurityCodeTrigger::badgerQuickLogin)->setUser($user);

    $this->em->persist($securityCode);
    $this->em->flush();

    return new JsonResponse(["securityCode" => $securityCode->getCode()]);
  }

}
