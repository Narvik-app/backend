<?php

namespace App\Enum;

enum ClubJobStatus: string {
  case in_progress = 'in_progress';
  case finished = 'finished';
  case failed = 'failed';
}
