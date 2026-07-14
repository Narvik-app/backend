<?php

namespace App\Service\PaymentTerminal;

use App\Entity\ClubDependent\Plugin\Sale\SalePaymentTerminalConnection;
use App\Service\PaymentTerminal\Credentials\TerminalCredentialsInterface;
use Psr\Log\LoggerInterface;

abstract class AbstractPaymentTerminalProvider implements PaymentTerminalProviderInterface {
  public function __construct(
    protected readonly LoggerInterface $logger,
  ) {
  }

  /**
   * Decode and validate credentials from the connection entity, optionally merging
   * in a specific device id (e.g. SumUp's readerId) for calls that target one device.
   */
  protected function credentialsOf(SalePaymentTerminalConnection $connection, ?string $deviceId = null): TerminalCredentialsInterface {
    $raw = $connection->getCredentials();
    if (empty($raw)) {
      throw new PaymentTerminalException('Connection "'.$connection->getName().'" has no credentials configured.');
    }
    if ($deviceId !== null) {
      $raw = $this->withDeviceId($raw, $deviceId);
    }
    return $this->credentialsFromArray($raw);
  }

  public function credentialsForDevice(SalePaymentTerminalConnection $connection, string $deviceId): TerminalCredentialsInterface {
    return $this->credentialsOf($connection, $deviceId);
  }

  /**
   * Merge a device id into a raw credentials map under the provider-specific key
   * (e.g. `readerId` for SumUp). Overridden by providers whose device id key differs.
   */
  protected function withDeviceId(array $credentials, string $deviceId): array {
    $credentials['readerId'] = $deviceId;
    return $credentials;
  }

  /**
   * Convert a decimal amount string (e.g. "15.00") to integer minor units (e.g. 1500 for EUR cents).
   */
  protected function toMinorUnits(string $amount, int $exponent = 2): int {
    return (int) round(floatval($amount) * (10 ** $exponent));
  }

  /**
   * Providers opt in to device listing by overriding this and listDevices().
   */
  public function canListDevices(): bool {
    return false;
  }

  public function listDevices(\App\Service\PaymentTerminal\Credentials\TerminalCredentialsInterface $credentials): array {
    throw new PaymentTerminalException('Ce fournisseur ne permet pas de lister les terminaux.');
  }

  public function getDeviceStatus(\App\Service\PaymentTerminal\Credentials\TerminalCredentialsInterface $credentials): \App\Service\PaymentTerminal\Dto\TerminalDevice {
    throw new PaymentTerminalException('Ce fournisseur ne permet pas de vérifier le statut du terminal.');
  }
}
