<?php

namespace App\Controller\ClubDependent\Plugin\Sale;

use App\Controller\Abstract\AbstractClubDependentController;
use App\Enum\SalePaymentTerminalProvider;
use App\Service\PaymentTerminal\PaymentTerminalException;
use App\Service\PaymentTerminal\PaymentTerminalManager;
use App\Service\RequestService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Provider-agnostic device discovery used while adding/editing a connection.
 *
 * Accepts a provider + raw credentials, validates them by listing the devices
 * attached to the account, and returns each device with its live online status.
 * Doubles as the credential check (a successful list means valid credentials).
 */
class SalePaymentTerminalConnectionListDevices extends AbstractClubDependentController {
  public function __construct(
    RequestService $requestService,
    private readonly PaymentTerminalManager $terminalManager,
  ) {
    parent::__construct($requestService);
  }

  public function __invoke(Request $request): JsonResponse {
    $payload = $this->checkAndGetJsonValues($request, ['provider', 'credentials']);

    $provider = SalePaymentTerminalProvider::tryFrom((string) $payload['provider']);
    if ($provider === null) {
      throw new HttpException(Response::HTTP_BAD_REQUEST, 'Fournisseur de terminal inconnu.');
    }

    $credentialsData = $payload['credentials'];
    if (!is_array($credentialsData)) {
      throw new HttpException(Response::HTTP_BAD_REQUEST, 'The "credentials" field must be an object.');
    }

    $providerImpl = $this->terminalManager->forProvider($provider);

    if (!$providerImpl->canListDevices()) {
      return new JsonResponse(['canList' => false, 'devices' => []]);
    }

    try {
      $credentials = $providerImpl->credentialsFromArray($credentialsData);
    }
    catch (\InvalidArgumentException $e) {
      throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, $e->getMessage(), $e);
    }

    try {
      $devices = $providerImpl->listDevices($credentials);
    }
    catch (PaymentTerminalException $e) {
      // Invalid credentials / provider unreachable
      throw new HttpException(Response::HTTP_BAD_GATEWAY, $e->getMessage(), $e);
    }

    return new JsonResponse([
      'canList' => true,
      'devices' => array_map(fn($device) => $device->toArray(), $devices),
    ]);
  }
}
