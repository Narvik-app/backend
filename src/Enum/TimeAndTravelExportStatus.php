<?php

namespace App\Enum;

enum TimeAndTravelExportStatus: string {
  case draft = 'draft';
  case locked = 'locked';
}
