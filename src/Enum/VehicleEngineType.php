<?php

namespace App\Enum;

enum VehicleEngineType: string {
  case petrol = 'petrol';
  case diesel = 'diesel';
  case electric = 'electric';
  case hybrid = 'hybrid';
}
