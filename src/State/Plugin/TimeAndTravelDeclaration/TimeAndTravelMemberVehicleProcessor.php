<?php

namespace App\State\Plugin\TimeAndTravelDeclaration;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\MemberVehicle;
use App\Repository\ClubDependent\Plugin\TimeAndTravelDeclaration\MemberVehicleRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Blocks deleting a vehicle still referenced by a declaration — the FK is
 * SET NULL on delete, which would otherwise silently orphan declarations that
 * require a vehicle. Suggest disabling the vehicle instead.
 */
class TimeAndTravelMemberVehicleProcessor implements ProcessorInterface {
  public function __construct(
    #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
    private readonly ProcessorInterface $persistProcessor,
    #[Autowire(service: 'api_platform.doctrine.orm.state.remove_processor')]
    private readonly ProcessorInterface $removeProcessor,
    private readonly MemberVehicleRepository $memberVehicleRepository,
  ) {
  }

  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed {
    if ($operation instanceof DeleteOperationInterface) {
      if ($data instanceof MemberVehicle && $this->memberVehicleRepository->isReferencedByDeclarations($data)) {
        throw new HttpException(Response::HTTP_CONFLICT, 'This vehicle has declarations attached to it, disable it instead of deleting it.');
      }

      return $this->removeProcessor->process($data, $operation, $uriVariables, $context);
    }

    return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
  }
}
