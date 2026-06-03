<?php

namespace App\Service\PaymentTerminal;

class PaymentTerminalException extends \RuntimeException {
  public function __construct(string $message, ?\Throwable $previous = null) {
    parent::__construct($message, 0, $previous);
  }
}
