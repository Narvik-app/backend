<?php

namespace App\Enum;

enum EmailStatus: string {
  case DRAFT = 'DRAFT';
  case SENT = 'SENT';
  case FAILED = 'FAILED';
}
