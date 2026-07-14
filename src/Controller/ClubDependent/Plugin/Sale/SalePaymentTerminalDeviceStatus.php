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
 * "Test connection" for an existing device: returns its live status using the
 * owning connection's stored credentials merged with this device's id.
 */
class SalePaymentTerminalDeviceStatus extends AbstractClubDependentController {
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
    $connection = $salePaymentTerminal->getConnection();
    if (!$connection->isConfigured()) {
      throw new HttpException(Response::HTTP_BAD_REQUEST, 'This terminal\'s connection is not configured.');
    }

    $providerImpl = $this->terminalManager->forConnection($connection);

    try {
      $credentials = $providerImpl->credentialsForDevice($connection, $salePaymentTerminal->getExternalDeviceId());
      $device = $providerImpl->getDeviceStatus($credentials);
    }
    catch (\InvalidArgumentException $e) {
      throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, $e->getMessage(), $e);
    }
    catch (PaymentTerminalException $e) {
      throw new HttpException(Response::HTTP_BAD_GATEWAY, $e->getMessage(), $e);
    }

    return new JsonResponse($device->toArray());
  }
}
