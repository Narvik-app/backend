<?php

namespace App\Controller\ClubDependent\Plugin\TimeAndTravelDeclaration;

use App\Controller\Abstract\AbstractController;
use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelExport;
use App\Enum\TimeAndTravelExportStatus;
use App\Service\TimeAndTravelExportGenerationService;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TimeAndTravelExportRegenerate extends AbstractController {
  public function __construct(
    private readonly TimeAndTravelExportGenerationService $generationService,
  ) {
  }

  public function __invoke(#[MapEntity(mapping: ['uuid' => 'uuid'])] TimeAndTravelExport $export): Response {
    if ($export->getStatus() !== TimeAndTravelExportStatus::draft) {
      throw new HttpException(Response::HTTP_CONFLICT, 'Only a draft export can be regenerated.');
    }

    $this->generationService->regenerate($export);

    return new Response();
  }
}
