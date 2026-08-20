<?php

namespace App\Enum;

enum ClubJobStatus: string {
  case IN_PROGRESS = 'in_progress';
  case FINISHED = 'finished';
  case FAILED = 'failed';
}
