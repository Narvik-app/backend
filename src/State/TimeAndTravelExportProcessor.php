<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelExport;
use App\Service\RequestService;
use App\Service\TimeAndTravelExportGenerationService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Creates a draft export: persists it, stamps who generated it, then
 * immediately runs the generation (attach declarations + render both PDF kinds).
 */
class TimeAndTravelExportProcessor implements ProcessorInterface {
  public function __construct(
    #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
    private readonly ProcessorInterface $persistProcessor,
    private readonly RequestService $requestService,
    private readonly TimeAndTravelExportGenerationService $generationService,
  ) {
  }

  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed {
    if (!$data instanceof TimeAndTravelExport) {
      return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }

    $activeProfile = $this->requestService->getActiveProfile();
    if ($activeProfile) {
      $data->setGeneratedBy($activeProfile->getMember());
    }

    $data = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

    $this->generationService->generate($data);

    return $data;
  }
}
