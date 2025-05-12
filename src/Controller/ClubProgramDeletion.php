<?php

namespace App\Controller;

use App\Controller\Abstract\AbstractController;
use App\Entity\Club;
use App\Service\ClubService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;

class ClubProgramDeletion extends AbstractController {

  public function __invoke(#[MapEntity(mapping: ['uuid' => 'uuid'])] Club $club, EntityManagerInterface $entityManager, ClubService $clubService): Club {
    $clubService->programDeletion($club);
    return $club;
  }

}
