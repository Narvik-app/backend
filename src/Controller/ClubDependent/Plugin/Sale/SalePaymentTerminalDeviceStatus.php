<?php

namespace App\Controller\ClubDependent\Plugin\Sale;

use App\Controller\Abstract\AbstractSalePaymentTerminalController;
use App\Entity\ClubDependent\Plugin\Sale\SalePaymentTerminal;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * "Test connection" for an existing device: returns its live status using the
 * owning connection's stored credentials merged with this device's id.
 */
class SalePaymentTerminalDeviceStatus extends AbstractSalePaymentTerminalController {
  public function __invoke(
    Request $request,
    #[MapEntity(mapping: ['uuid' => 'uuid'])] SalePaymentTerminal $salePaymentTerminal,
  ): JsonResponse {
    $connection = $salePaymentTerminal->getConnection();
    if (!$connection->isConfigured()) {
      throw new HttpException(Response::HTTP_BAD_REQUEST, 'This terminal\'s connection is not configured.');
    }

    $providerImpl = $this->terminalManager->forConnection($connection);

    $device = $this->callTerminal(function () use ($providerImpl, $connection, $salePaymentTerminal) {
      $credentials = $providerImpl->credentialsForDevice($connection, $salePaymentTerminal->getExternalDeviceId());
      return $providerImpl->getDeviceStatus($credentials);
    });

    return new JsonResponse($device->toArray());
  }
}
