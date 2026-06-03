<?php

namespace App\Controller\ClubDependent\Plugin\Sale;

use App\Controller\Abstract\AbstractClubDependentController;
use App\Entity\ClubDependent\Plugin\Sale\SalePaymentTerminal;
use App\Service\PaymentTerminal\PaymentTerminalManager;
use App\Service\RequestService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Select the device for an existing terminal without re-sending the secret
 * credentials: merges the chosen device id into the stored (encrypted) credentials.
 */
class SalePaymentTerminalSetDevice extends AbstractClubDependentController {
  public function __construct(
    RequestService $requestService,
    private readonly PaymentTerminalManager $terminalManager,
    private readonly EntityManagerInterface $entityManager,
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

    $payload = $this->checkAndGetJsonValues($request, ['deviceId']);
    $deviceId = (string) $payload['deviceId'];
    if ($deviceId === '') {
      throw new HttpException(Response::HTTP_BAD_REQUEST, 'deviceId est requis.');
    }

    $providerImpl = $this->terminalManager->forTerminal($salePaymentTerminal);

    $newCredentials = $providerImpl->withDevice($salePaymentTerminal->getCredentials() ?? [], $deviceId);

    try {
      $providerImpl->validateCredentials($newCredentials);
    }
    catch (\InvalidArgumentException $e) {
      throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, $e->getMessage(), $e);
    }

    $salePaymentTerminal->setCredentials($newCredentials);
    $this->entityManager->flush();

    return new JsonResponse(['configured' => true]);
  }
}
