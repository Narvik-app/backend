<?php

namespace App\Service\PaymentTerminal;

use App\Entity\ClubDependent\Plugin\Sale\SalePaymentTerminal;
use App\Enum\SalePaymentTerminalProvider;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Central registry for payment terminal providers.
 *
 * To add a new provider, implement PaymentTerminalProviderInterface (tagged
 * automatically via #[AutoconfigureTag('app.payment_terminal_provider')]) and
 * add a new case to SalePaymentTerminalProvider enum. No config changes needed.
 */
class PaymentTerminalManager {
  /** @var array<string, PaymentTerminalProviderInterface> */
  private array $providers = [];

  /**
   * @param iterable<PaymentTerminalProviderInterface> $providerServices
   */
  public function __construct(
    #[AutowireIterator('app.payment_terminal_provider')]
    iterable $providerServices,
  ) {
    foreach ($providerServices as $provider) {
      $this->providers[$provider->getProvider()->value] = $provider;
    }
  }

  /**
   * Resolve the provider implementation for a given terminal.
   *
   * @throws \InvalidArgumentException if no provider is registered for the terminal's provider type
   */
  public function forTerminal(SalePaymentTerminal $terminal): PaymentTerminalProviderInterface {
    return $this->forProvider($terminal->getProvider());
  }

  /**
   * Resolve a provider implementation by provider enum value.
   *
   * @throws \InvalidArgumentException
   */
  public function forProvider(SalePaymentTerminalProvider $provider): PaymentTerminalProviderInterface {
    $impl = $this->providers[$provider->value] ?? null;
    if ($impl === null) {
      throw new \InvalidArgumentException(
        "No payment terminal provider registered for '{$provider->value}'. ".
        'Implement PaymentTerminalProviderInterface and tag it with #[AutoconfigureTag(\'app.payment_terminal_provider\')].',
      );
    }
    return $impl;
  }
}
