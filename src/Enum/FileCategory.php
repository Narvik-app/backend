<?php

namespace App\Enum;

enum FileCategory: string {
  case logo = 'logo';
  case legals = 'legals';

  case member_picture = 'member_picture';

  case club_email = 'club_email';
}
