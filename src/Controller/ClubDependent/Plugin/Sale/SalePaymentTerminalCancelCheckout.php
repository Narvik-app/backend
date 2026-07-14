<?php

namespace App\Controller\ClubDependent\Plugin\Sale;

use App\Controller\Abstract\AbstractSalePaymentTerminalController;
use App\Entity\ClubDependent\Plugin\Sale\SalePaymentTerminal;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class SalePaymentTerminalCancelCheckout extends AbstractSalePaymentTerminalController {
  public function __invoke(
    Request $request,
    #[MapEntity(mapping: ['uuid' => 'uuid'])] SalePaymentTerminal $salePaymentTerminal,
  ): JsonResponse {
    $connection = $salePaymentTerminal->getConnection();

    $this->callTerminal(fn() => $this->terminalManager->forConnection($connection)->cancelCheckout(
      $connection,
      $salePaymentTerminal->getExternalDeviceId(),
    ));

    return new JsonResponse(['cancelled' => true]);
  }
}
