<?php

namespace App\Service\PaymentTerminal;

use App\Entity\ClubDependent\Plugin\Sale\SalePaymentTerminal;
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
   * Initiate a payment on the terminal for the given amount (decimal string, e.g. "15.00").
   *
   * @throws PaymentTerminalException
   */
  public function createCheckout(SalePaymentTerminal $terminal, string $amount, string $description): TerminalCheckoutResult;

  /**
   * Poll the status of a previously initiated checkout.
   *
   * @throws PaymentTerminalException
   */
  public function getCheckoutStatus(SalePaymentTerminal $terminal, string $clientTransactionId): TerminalCheckoutStatusResult;
}
