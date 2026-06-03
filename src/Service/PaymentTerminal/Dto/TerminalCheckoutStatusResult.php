<?php

namespace App\Service\PaymentTerminal\Dto;

use App\Enum\SalePaymentTerminalCheckoutStatus;

final readonly class TerminalCheckoutStatusResult {
  public function __construct(
    public SalePaymentTerminalCheckoutStatus $status,
    public ?string $transactionId = null,
  ) {
  }
}
