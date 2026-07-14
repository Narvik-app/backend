<?php

namespace App\Controller\ClubDependent\Plugin\Sale;

use App\Controller\Abstract\AbstractClubDependentController;
use App\Entity\ClubDependent\Plugin\Sale\SalePaymentTerminal;
use App\Service\PaymentTerminal\PaymentTerminalException;
use App\Service\PaymentTerminal\PaymentTerminalManager;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SalePaymentTerminalCheckoutStatus extends AbstractClubDependentController {
  public function __construct(
    \App\Service\RequestService $requestService,
    private readonly PaymentTerminalManager $terminalManager,
  ) {
    parent::__construct($requestService);
  }

  public function __invoke(
    Request $request,
    #[MapEntity(mapping: ['uuid' => 'uuid'])] SalePaymentTerminal $salePaymentTerminal,
  ): JsonResponse {
    $clientTransactionId = $request->query->get('clientTransactionId');
    if (empty($clientTransactionId)) {
      throw new HttpException(Response::HTTP_BAD_REQUEST, 'Missing required query parameter: clientTransactionId');
    }

    $connection = $salePaymentTerminal->getConnection();

    try {
      $result = $this->terminalManager->forConnection($connection)->getCheckoutStatus(
        $connection,
        (string) $clientTransactionId,
      );
    }
    catch (PaymentTerminalException $e) {
      throw new HttpException(Response::HTTP_BAD_GATEWAY, $e->getMessage(), $e);
    }

    return new JsonResponse([
      'status' => $result->status->value,
      'transactionId' => $result->transactionId,
    ]);
  }
}
