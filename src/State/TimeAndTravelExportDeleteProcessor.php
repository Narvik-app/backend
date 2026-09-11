<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelExport;
use App\Enum\Permission;
use App\Enum\TimeAndTravelExportStatus;
use App\Service\FileService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * A locked export additionally requires TIME_TRAVEL_UNLOCK to delete — the
 * base security expression only checks TIME_TRAVEL_EXPORT, which is not
 * enough to destroy an official, already-locked record.
 */
class TimeAndTravelExportDeleteProcessor implements ProcessorInterface {
  public function __construct(
    #[Autowire(service: 'api_platform.doctrine.orm.state.remove_processor')]
    private readonly ProcessorInterface $removeProcessor,
    private readonly AuthorizationCheckerInterface $authorizationChecker,
    private readonly EntityManagerInterface $entityManager,
    private readonly FileService $fileService,
  ) {
  }

  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed {
    if (!$data instanceof TimeAndTravelExport) {
      return $this->removeProcessor->process($data, $operation, $uriVariables, $context);
    }

    if ($data->getStatus() === TimeAndTravelExportStatus::locked
      && !$this->authorizationChecker->isGranted(Permission::TIME_TRAVEL_UNLOCK->value, $data)
    ) {
      throw new HttpException(Response::HTTP_FORBIDDEN, 'Unlocking permission required to delete a locked export.');
    }

    foreach ($data->getDeclarations() as $declaration) {
      $declaration->setExport(null);
    }

    if ($data->getRecapFile()) {
      $this->fileService->remove($data->getRecapFile());
      $this->entityManager->remove($data->getRecapFile());
    }

    foreach ($data->getAttestations() as $attestation) {
      if ($attestation->getFile()) {
        $this->fileService->remove($attestation->getFile());
        $this->entityManager->remove($attestation->getFile());
      }
    }

    return $this->removeProcessor->process($data, $operation, $uriVariables, $context);
  }
}
