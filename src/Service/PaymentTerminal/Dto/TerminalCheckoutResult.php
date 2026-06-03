<?php

namespace App\Service\PaymentTerminal\Dto;

final readonly class TerminalCheckoutResult {
  public function __construct(
    public string $clientTransactionId,
    public ?string $providerCheckoutId = null,
  ) {
  }
}
