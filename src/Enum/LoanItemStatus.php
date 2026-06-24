<?php

namespace App\Enum;

enum LoanItemStatus: string {
  case available = 'available';
  case maintenance = 'maintenance';
  case sold = 'sold';
  case retired = 'retired';
}
