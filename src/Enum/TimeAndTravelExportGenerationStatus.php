<?php

namespace App\Enum;

enum TimeAndTravelExportGenerationStatus: string {
  case idle = 'idle';
  case generating = 'generating';
  case failed = 'failed';
}
