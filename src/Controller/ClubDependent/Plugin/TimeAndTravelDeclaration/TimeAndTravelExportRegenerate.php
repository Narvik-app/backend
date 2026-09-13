<?php

namespace App\Controller\ClubDependent\Plugin\TimeAndTravelDeclaration;

use App\Controller\Abstract\AbstractController;
use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelExport;
use App\Enum\TimeAndTravelExportStatus;
use App\Message\TimeAndTravelExportRegenerateMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Messenger\MessageBusInterface;

class TimeAndTravelExportRegenerate extends AbstractController {
  public function __construct(
    private readonly EntityManagerInterface $entityManager,
    private readonly MessageBusInterface $messageBus,
  ) {
  }

  public function __invoke(#[MapEntity(mapping: ['uuid' => 'uuid'])] TimeAndTravelExport $export): Response {
    if ($export->getStatus() !== TimeAndTravelExportStatus::draft) {
      throw new HttpException(Response::HTTP_CONFLICT, 'Only a draft export can be regenerated.');
    }

    // Runs in the background like locking does — see TimeAndTravelExportLock — so a big export
    // doesn't block the request on PDF rendering for every member.
    $export->setIsRegenerating(true);
    $this->entityManager->flush();

    $this->messageBus->dispatch(new TimeAndTravelExportRegenerateMessage($export->getUuid()->toString()));

    return new Response(status: Response::HTTP_ACCEPTED);
  }
}
