<?php

namespace App\Controller\ClubDependent\Plugin\Sale;

use App\Controller\Abstract\AbstractSalePaymentTerminalController;
use App\Entity\ClubDependent\Plugin\Sale\SalePaymentTerminal;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SalePaymentTerminalCheckoutStatus extends AbstractSalePaymentTerminalController {
  public function __invoke(
    Request $request,
    #[MapEntity(mapping: ['uuid' => 'uuid'])] SalePaymentTerminal $salePaymentTerminal,
  ): JsonResponse {
    $clientTransactionId = $request->query->get('clientTransactionId');
    if (empty($clientTransactionId)) {
      throw new HttpException(Response::HTTP_BAD_REQUEST, 'Missing required query parameter: clientTransactionId');
    }

    $connection = $salePaymentTerminal->getConnection();

    $result = $this->callTerminal(fn() => $this->terminalManager->forConnection($connection)->getCheckoutStatus(
      $connection,
      (string) $clientTransactionId,
    ));

    return new JsonResponse([
      'status' => $result->status->value,
      'transactionId' => $result->transactionId,
    ]);
  }
}
