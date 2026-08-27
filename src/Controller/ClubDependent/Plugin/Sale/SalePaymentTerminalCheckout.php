<?php

namespace App\Controller\ClubDependent\Plugin\Sale;

use App\Controller\Abstract\AbstractSalePaymentTerminalController;
use App\Entity\ClubDependent\Plugin\Sale\SalePaymentTerminal;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SalePaymentTerminalCheckout extends AbstractSalePaymentTerminalController {
  public function __invoke(
    Request $request,
    #[MapEntity(mapping: ['uuid' => 'uuid'])] SalePaymentTerminal $salePaymentTerminal,
  ): JsonResponse {
    if (!$salePaymentTerminal->isUsable()) {
      throw new HttpException(Response::HTTP_BAD_REQUEST, 'This payment terminal is not available or not configured.');
    }

    $payload = $this->checkAndGetJsonValues($request, ['amount']);
    $amount = (string) $payload['amount'];
    $description = isset($payload['description'])
      ? (string) $payload['description']
      : ($salePaymentTerminal->getName() !== null ? sprintf('Vente %s', $salePaymentTerminal->getName()) : 'Vente');

    $connection = $salePaymentTerminal->getConnection();

    $result = $this->callTerminal(fn() => $this->terminalManager->forConnection($connection)->createCheckout(
      $connection,
      $salePaymentTerminal->getExternalDeviceId(),
      $amount,
      $description,
    ));

    return new JsonResponse([
      'clientTransactionId' => $result->clientTransactionId,
      'providerCheckoutId' => $result->providerCheckoutId,
    ]);
  }
}
