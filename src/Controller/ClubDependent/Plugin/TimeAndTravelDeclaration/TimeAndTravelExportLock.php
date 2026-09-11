<?php

namespace App\Controller\ClubDependent\Plugin\TimeAndTravelDeclaration;

use App\Controller\Abstract\AbstractController;
use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelExport;
use App\Enum\TimeAndTravelExportStatus;
use App\Message\TimeAndTravelExportRegenerateMessage;
use App\Service\RequestService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Messenger\MessageBusInterface;

class TimeAndTravelExportLock extends AbstractController {
  public function __construct(
    private readonly EntityManagerInterface $entityManager,
    private readonly RequestService $requestService,
    private readonly MessageBusInterface $messageBus,
  ) {
  }

  public function __invoke(#[MapEntity(mapping: ['uuid' => 'uuid'])] TimeAndTravelExport $export): Response {
    if ($export->getStatus() !== TimeAndTravelExportStatus::draft) {
      throw new HttpException(Response::HTTP_CONFLICT, 'Only a draft export can be locked.');
    }

    // Declarations can be added right up until locking, so regeneration (picking up every last
    // declaration) and the lock itself both happen in the background job — see FEATURE_MERCURE.md
    // for how the frontend is meant to learn when this finishes.
    $export->setIsRegenerating(true);
    $this->entityManager->flush();

    $activeProfile = $this->requestService->getActiveProfile();
    $this->messageBus->dispatch(new TimeAndTravelExportRegenerateMessage(
      $export->getUuid()->toString(),
      lockAfter: true,
      lockedByMemberUuid: $activeProfile?->getMember()?->getUuid()->toString(),
    ));

    return new Response(status: Response::HTTP_ACCEPTED);
  }
}
