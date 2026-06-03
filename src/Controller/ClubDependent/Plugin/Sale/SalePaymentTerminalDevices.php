<?php

namespace App\Controller\ClubDependent\Plugin\Sale;

use App\Controller\Abstract\AbstractClubDependentController;
use App\Entity\ClubDependent\Plugin\Sale\SalePaymentTerminal;
use App\Service\PaymentTerminal\PaymentTerminalException;
use App\Service\PaymentTerminal\PaymentTerminalManager;
use App\Service\RequestService;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * List the devices available for an EXISTING terminal, using its stored
 * credentials (used during reconfiguration so secrets need not be re-entered).
 */
class SalePaymentTerminalDevices extends AbstractClubDependentController {
  public function __construct(
    RequestService $requestService,
    private readonly PaymentTerminalManager $terminalManager,
  ) {
    parent::__construct($requestService);
  }

  public function __invoke(
    Request $request,
    #[MapEntity(mapping: ['uuid' => 'uuid'])] SalePaymentTerminal $salePaymentTerminal,
  ): JsonResponse {
    if (!$salePaymentTerminal->isConfigured()) {
      throw new HttpException(Response::HTTP_BAD_REQUEST, 'Ce terminal de paiement n\'est pas configuré.');
    }

    $providerImpl = $this->terminalManager->forTerminal($salePaymentTerminal);

    if (!$providerImpl->canListDevices()) {
      return new JsonResponse(['canList' => false, 'devices' => []]);
    }

    try {
      $credentials = $providerImpl->credentialsFromArray($salePaymentTerminal->getCredentials());
      $devices = $providerImpl->listDevices($credentials);
    }
    catch (\InvalidArgumentException $e) {
      throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, $e->getMessage(), $e);
    }
    catch (PaymentTerminalException $e) {
      throw new HttpException(Response::HTTP_BAD_GATEWAY, $e->getMessage(), $e);
    }

    return new JsonResponse([
      'canList' => true,
      'devices' => array_map(fn($device) => $device->toArray(), $devices),
    ]);
  }
}
