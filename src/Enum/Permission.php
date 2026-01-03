<?php

namespace App\Enum;

enum Permission: string {
  case EMAIL_SEND = 'EMAIL_SEND';
  case EMAIL_TEMPLATE = 'EMAIL_TEMPLATE';
  case IMPORT_MEMBERS = 'IMPORT_MEMBERS';
  case IMPORT_PHOTOS = 'IMPORT_PHOTOS';
  case IMPORT_PRESENCES = 'IMPORT_PRESENCES';

  /**
   * Returns all available permissions as an array of values
   * @return string[]
   */
  public static function values(): array {
    return array_map(fn(Permission $p) => $p->value, self::cases());
  }
}
