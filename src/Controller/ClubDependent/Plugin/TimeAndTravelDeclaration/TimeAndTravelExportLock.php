<?php

namespace App\Controller\ClubDependent\Plugin\TimeAndTravelDeclaration;

use App\Controller\Abstract\AbstractController;
use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelExport;
use App\Enum\TimeAndTravelExportStatus;
use App\Service\RequestService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TimeAndTravelExportLock extends AbstractController {
  public function __construct(
    private readonly EntityManagerInterface $entityManager,
    private readonly RequestService $requestService,
  ) {
  }

  public function __invoke(#[MapEntity(mapping: ['uuid' => 'uuid'])] TimeAndTravelExport $export): Response {
    if ($export->getStatus() !== TimeAndTravelExportStatus::draft) {
      throw new HttpException(Response::HTTP_CONFLICT, 'Only a draft export can be locked.');
    }

    $activeProfile = $this->requestService->getActiveProfile();

    $export
      ->setStatus(TimeAndTravelExportStatus::locked)
      ->setLockedAt(new \DateTimeImmutable())
      ->setLockedBy($activeProfile?->getMember());

    $this->entityManager->flush();

    return new Response();
  }
}
