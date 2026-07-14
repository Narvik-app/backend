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

class SalePaymentTerminalCancelCheckout extends AbstractClubDependentController {
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
    $connection = $salePaymentTerminal->getConnection();

    try {
      $this->terminalManager->forConnection($connection)->cancelCheckout(
        $connection,
        $salePaymentTerminal->getExternalDeviceId(),
      );
    }
    catch (PaymentTerminalException $e) {
      throw new HttpException(Response::HTTP_BAD_GATEWAY, $e->getMessage(), $e);
    }

    return new JsonResponse(['cancelled' => true]);
  }
}
