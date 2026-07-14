<?php

namespace App\Controller\ClubDependent\Plugin\Sale;

use App\Controller\Abstract\AbstractClubDependentController;
use App\Entity\ClubDependent\Plugin\Sale\SalePaymentTerminal;
use App\Entity\ClubDependent\Plugin\Sale\SalePaymentTerminalConnection;
use App\Service\PaymentTerminal\PaymentTerminalException;
use App\Service\PaymentTerminal\PaymentTerminalManager;
use App\Service\RequestService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Discover all devices available on a connection and upsert matching
 * SalePaymentTerminal rows: creates new ones (name defaulted from the
 * provider's own device name), and stamps lastSeenAt on every device still
 * returned by the provider. Existing devices no longer returned are left
 * untouched — never deleted — so a device's local overrides (name, icon,
 * payment mode) are never silently lost; the frontend flags them as stale by
 * comparing lastSeenAt to the connection's lastSyncedAt (stamped either way).
 */
class SalePaymentTerminalConnectionSyncDevices extends AbstractClubDependentController {
  public function __construct(
    RequestService $requestService,
    private readonly PaymentTerminalManager $terminalManager,
    private readonly EntityManagerInterface $entityManager,
  ) {
    parent::__construct($requestService);
  }

  public function __invoke(
    Request $request,
    #[MapEntity(mapping: ['uuid' => 'uuid'])] SalePaymentTerminalConnection $salePaymentTerminalConnection,
  ): JsonResponse {
    if (!$salePaymentTerminalConnection->isConfigured()) {
      throw new HttpException(Response::HTTP_BAD_REQUEST, 'This connection is not configured.');
    }

    $providerImpl = $this->terminalManager->forConnection($salePaymentTerminalConnection);

    if (!$providerImpl->canListDevices()) {
      throw new HttpException(Response::HTTP_BAD_REQUEST, 'This provider does not support device listing.');
    }

    $now = new \DateTimeImmutable();

    try {
      $credentials = $providerImpl->credentialsFromArray($salePaymentTerminalConnection->getCredentials());
      $devices = $providerImpl->listDevices($credentials);
    }
    catch (\InvalidArgumentException $e) {
      throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, $e->getMessage(), $e);
    }
    catch (PaymentTerminalException $e) {
      throw new HttpException(Response::HTTP_BAD_GATEWAY, $e->getMessage(), $e);
    }
    finally {
      // Stamped whether the sync succeeded or failed, so stale-device flagging
      // always reflects the most recent attempt.
      $salePaymentTerminalConnection->setLastSyncedAt($now);
      $this->entityManager->flush();
    }

    /** @var array<string, SalePaymentTerminal> $existingByExternalId */
    $existingByExternalId = [];
    foreach ($salePaymentTerminalConnection->getDevices() as $existing) {
      $existingByExternalId[$existing->getExternalDeviceId()] = $existing;
    }

    $createdCount = 0;
    foreach ($devices as $device) {
      $terminal = $existingByExternalId[$device->id] ?? null;
      if ($terminal === null) {
        $terminal = new SalePaymentTerminal();
        $terminal->setClub($salePaymentTerminalConnection->getClub());
        $terminal->setConnection($salePaymentTerminalConnection);
        $terminal->setExternalDeviceId($device->id);
        $terminal->setName($device->name);
        $terminal->setAvailable(true);
        $this->entityManager->persist($terminal);
        $createdCount++;
      }
      $terminal->setLastSeenAt($now);
    }

    $this->entityManager->flush();

    return new JsonResponse([
      'lastSyncedAt' => $salePaymentTerminalConnection->getLastSyncedAt()?->format(\DATE_ATOM),
      'devicesFound' => count($devices),
      'devicesCreated' => $createdCount,
    ]);
  }
}
