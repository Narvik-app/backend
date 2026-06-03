<?php

namespace App\Service\PaymentTerminal;

use App\Entity\ClubDependent\Plugin\Sale\SalePaymentTerminal;
use App\Service\PaymentTerminal\Credentials\TerminalCredentialsInterface;
use Psr\Log\LoggerInterface;

abstract class AbstractPaymentTerminalProvider implements PaymentTerminalProviderInterface {
  public function __construct(
    protected readonly LoggerInterface $logger,
  ) {
  }

  /**
   * Decode and validate credentials from the terminal entity.
   */
  protected function credentialsOf(SalePaymentTerminal $terminal): TerminalCredentialsInterface {
    $raw = $terminal->getCredentials();
    if (empty($raw)) {
      throw new PaymentTerminalException('Terminal "'.$terminal->getName().'" has no credentials configured.');
    }
    return $this->credentialsFromArray($raw);
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
