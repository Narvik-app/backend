<?php

namespace App\Enum;

enum FileCategory: string {
  case logo = 'logo';
  case legals = 'legals';

  case member_picture = 'member_picture';

  case loan_item_picture = 'loan_item_picture';

  case club_email = 'club_email';

  case time_and_travel_recap = 'time_and_travel_recap';
  case time_and_travel_attestation = 'time_and_travel_attestation';
}
