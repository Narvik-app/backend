<?php

namespace App\Enum;

enum GlobalSetting {
  // Legals last update
  case LEGALS_LAST_UPDATE;

  // Email configuration
  case SMTP_ON;
  case SMTP_HOST;
  case SMTP_PORT;
  case SMTP_USERNAME;
  case SMTP_PASSWORD;
  case SMTP_SENDER;
  case SMTP_SENDER_NAME;
}
