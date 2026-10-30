<?php

namespace App\Enum;

use App\Entity\Club;

enum ClubPlugin: string {
  case presences = 'Presences';
  case sales = 'Sales';
  case loans = 'Loans';
  case time_and_travel = 'TimeAndTravel';

  /**
   * Request paths (relative to /clubs/{id}) that require this plugin to be enabled.
   *
   * @return string[]
   */
  public function urlPrefixes(): array {
    return match ($this) {
      self::presences => ['/member-presences', '/external-presences'],
      self::sales => ['/sales', '/sale-payment-modes', '/inventory-'],
      self::loans => ['/loans', '/loan-items', '/loan-categories', '/loan-recordings', '/loan-recording-types'],
      self::time_and_travel => ['/time-and-travel-declarations', '/time-and-travel-exports', '/member-vehicles'],
    };
  }

  public function isEnabledFor(Club $club): bool {
    return match ($this) {
      self::presences => $club->getPresencesEnabled(),
      self::sales => (bool) $club->getSalesEnabled(),
      self::loans => $club->getLoansEnabled(),
      self::time_and_travel => $club->getTimeAndTravelEnabled(),
    };
  }

  /**
   * Plugin owning the given path, or null when the path is not plugin specific.
   */
  public static function fromPath(string $path): ?self {
    foreach (self::cases() as $plugin) {
      foreach ($plugin->urlPrefixes() as $prefix) {
        if (str_starts_with($path, $prefix)) {
          return $plugin;
        }
      }
    }

    return null;
  }
}
