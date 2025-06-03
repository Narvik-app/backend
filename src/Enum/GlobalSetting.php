<?php

namespace App\Enum;

enum GlobalSetting {
  // Legals last update
  case LEGALS_LAST_UPDATE;
  case LEGALS_CGU;
  case LEGALS_CGV;
  case LEGALS_PRIVACY_POLICY;

  // Email configuration
  case SMTP_ON;
  case SMTP_HOST;
  case SMTP_PORT;
  case SMTP_USERNAME;
  case SMTP_PASSWORD;
  case SMTP_SENDER;
  case SMTP_NEWSLETTER_SENDER;
  case SMTP_SENDER_NAME;
}
