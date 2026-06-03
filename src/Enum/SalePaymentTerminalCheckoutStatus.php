<?php

namespace App\Enum;

enum SalePaymentTerminalCheckoutStatus: string {
  case pending = 'pending';
  case successful = 'successful';
  case failed = 'failed';
  case cancelled = 'cancelled';
}
