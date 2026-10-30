<?php

namespace App\State\Plugin\TimeAndTravelDeclaration;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelExport;
use App\Enum\TimeAndTravelExportGenerationStatus;
use App\Message\TimeAndTravelExportRegenerateMessage;
use App\Service\RequestService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Creates a draft export: persists it, stamps who generated it, then queues the generation
 * (attach declarations + render both PDF kinds) in the background.
 */
class TimeAndTravelExportProcessor implements ProcessorInterface {
  public function __construct(
    #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
    private readonly ProcessorInterface $persistProcessor,
    private readonly RequestService $requestService,
    private readonly MessageBusInterface $messageBus,
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

    // The generation renders a PDF per member, which times out on a long period — so the export is
    // only created here, flagged as regenerating, and filled in by the background job.
    $data->setGenerationStatus(TimeAndTravelExportGenerationStatus::generating);

    $data = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

    $this->messageBus->dispatch(new TimeAndTravelExportRegenerateMessage($data->getUuid()->toString()));

    return $data;
  }
}
