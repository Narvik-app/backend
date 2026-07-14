<?php

namespace App\Service\PaymentTerminal;

use App\Entity\ClubDependent\Plugin\Sale\SalePaymentTerminalConnection;
use App\Enum\SalePaymentTerminalProvider;
use App\Service\PaymentTerminal\Credentials\TerminalCredentialsInterface;
use App\Service\PaymentTerminal\Dto\TerminalCheckoutResult;
use App\Service\PaymentTerminal\Dto\TerminalCheckoutStatusResult;
use App\Service\PaymentTerminal\Dto\TerminalDevice;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.payment_terminal_provider')]
interface PaymentTerminalProviderInterface {
  /**
   * Returns the provider this implementation handles.
   */
  public function getProvider(): SalePaymentTerminalProvider;

  /**
   * Decode a raw credentials array into a typed, provider-specific DTO.
   */
  public function credentialsFromArray(array $data): TerminalCredentialsInterface;

  /**
   * Validate a raw credentials map and throw \InvalidArgumentException on error.
   */
  public function validateCredentials(array $data): void;

  /**
   * Whether this provider can enumerate the physical devices attached to an account.
   */
  public function canListDevices(): bool;

  /**
   * List the devices available for the given credentials (used during setup).
   * Each device reports its reachability so the UI can show online/offline status.
   *
   * @return TerminalDevice[]
   * @throws PaymentTerminalException
   */
  public function listDevices(TerminalCredentialsInterface $credentials): array;

  /**
   * Fetch the live status of the single device configured on the given credentials
   * (used by the "test connection" action on an existing terminal).
   *
   * @throws PaymentTerminalException
   */
  public function getDeviceStatus(TerminalCredentialsInterface $credentials): TerminalDevice;

  /**
   * Build device-scoped credentials for a connection + device id (merges the device
   * id into the connection's stored credentials under the provider-specific key,
   * e.g. `readerId` for SumUp), for callers that need to call getDeviceStatus().
   *
   * @throws PaymentTerminalException if the connection has no credentials configured
   */
  public function credentialsForDevice(SalePaymentTerminalConnection $connection, string $deviceId): TerminalCredentialsInterface;

  /**
   * Initiate a payment on the given device (identified by its provider-side id,
   * e.g. SumUp's readerId) for the given amount (decimal string, e.g. "15.00").
   *
   * @throws PaymentTerminalException
   */
  public function createCheckout(SalePaymentTerminalConnection $connection, string $deviceId, string $amount, string $description): TerminalCheckoutResult;

  /**
   * Poll the status of a previously initiated checkout.
   *
   * @throws PaymentTerminalException
   */
  public function getCheckoutStatus(SalePaymentTerminalConnection $connection, string $clientTransactionId): TerminalCheckoutStatusResult;
}
