<?php

namespace App\Service\PaymentTerminal\Credentials;

use App\Enum\SalePaymentTerminalProvider;

interface TerminalCredentialsInterface extends \JsonSerializable {
  public function getProvider(): SalePaymentTerminalProvider;
}
