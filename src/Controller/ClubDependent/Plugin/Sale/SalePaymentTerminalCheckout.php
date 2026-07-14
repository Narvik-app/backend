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

class SalePaymentTerminalCheckout extends AbstractClubDependentController {
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
    if (!$salePaymentTerminal->isUsable()) {
      throw new HttpException(Response::HTTP_BAD_REQUEST, 'Ce terminal de paiement n\'est pas disponible ou n\'est pas configuré.');
    }

    $payload = $this->checkAndGetJsonValues($request, ['amount']);
    $amount = (string) $payload['amount'];
    $description = isset($payload['description']) ? (string) $payload['description'] : 'Vente';

    $connection = $salePaymentTerminal->getConnection();

    try {
      $result = $this->terminalManager->forConnection($connection)->createCheckout(
        $connection,
        $salePaymentTerminal->getExternalDeviceId(),
        $amount,
        $description,
      );
    }
    catch (PaymentTerminalException $e) {
      throw new HttpException(Response::HTTP_BAD_GATEWAY, $e->getMessage(), $e);
    }

    return new JsonResponse([
      'clientTransactionId' => $result->clientTransactionId,
      'providerCheckoutId' => $result->providerCheckoutId,
    ]);
  }
}
