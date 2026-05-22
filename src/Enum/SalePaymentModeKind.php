<?php

namespace App\Enum;

enum SalePaymentModeKind: string {
  case payment = 'payment';
  case stock_removal = 'stock_removal';
}
