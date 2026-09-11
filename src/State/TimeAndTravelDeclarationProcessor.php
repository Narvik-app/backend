<?php

namespace App\State;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelDeclaration;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Blocks deleting a locked declaration. Validation (TimeAndTravelDeclarationNotLocked)
 * already blocks POST/PATCH, but the validator never runs on DELETE.
 */
class TimeAndTravelDeclarationProcessor implements ProcessorInterface {
  public function __construct(
    #[Autowire(service: 'api_platform.doctrine.orm.state.remove_processor')]
    private readonly ProcessorInterface $removeProcessor,
  ) {
  }

  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed {
    if ($operation instanceof DeleteOperationInterface && $data instanceof TimeAndTravelDeclaration && $data->getIsLocked()) {
      throw new HttpException(Response::HTTP_CONFLICT, 'This declaration is locked, unlock its export first.');
    }

    return $this->removeProcessor->process($data, $operation, $uriVariables, $context);
  }
}
