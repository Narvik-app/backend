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

  /** Percentage bonus applied to the official barème kilométrique for electric vehicles (e.g. "0.20" for +20%). */
  case TIME_AND_TRAVEL_ELECTRIC_BONUS_RATE;

  public function isEncrypted(): bool {
    return match ($this) {
      self::SMTP_USERNAME, self::SMTP_PASSWORD => true,
      default => false,
    };
  }

  /**
   * Value must never be returned by the API
   */
  public function isSecret(): bool {
    return match ($this) {
      self::SMTP_PASSWORD => true,
      default => false,
    };
  }
}
