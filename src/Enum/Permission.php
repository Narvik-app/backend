<?php

namespace App\Enum;

enum Permission: string {
  // Email permissions
  case EMAIL_ACCESS = 'EMAIL_ACCESS';
  case EMAIL_EDIT = 'EMAIL_EDIT';

  // Email template permissions
  case EMAIL_TEMPLATE_ACCESS = 'EMAIL_TEMPLATE_ACCESS';
  case EMAIL_TEMPLATE_EDIT = 'EMAIL_TEMPLATE_EDIT';

  // Import permissions
  case IMPORT_MEMBERS_ACCESS = 'IMPORT_MEMBERS_ACCESS';
  case IMPORT_MEMBERS_EDIT = 'IMPORT_MEMBERS_EDIT';

  case IMPORT_PHOTOS_ACCESS = 'IMPORT_PHOTOS_ACCESS';
  case IMPORT_PHOTOS_EDIT = 'IMPORT_PHOTOS_EDIT';

  case IMPORT_PRESENCES_ACCESS = 'IMPORT_PRESENCES_ACCESS';
  case IMPORT_PRESENCES_EDIT = 'IMPORT_PRESENCES_EDIT';

  /**
   * Returns all available permissions as an array of values
   * @return string[]
   */
  public static function values(): array {
    return array_map(fn(Permission $p) => $p->value, self::cases());
  }

  /**
   * Get the ACCESS permission for an EDIT permission (hierarchy check)
   * EDIT permission implies ACCESS permission
   * @return Permission|null The corresponding ACCESS permission, or null if this is already ACCESS
   */
  public function getAccessPermission(): ?Permission {
    return match($this) {
      self::EMAIL_EDIT => self::EMAIL_ACCESS,
      self::EMAIL_TEMPLATE_EDIT => self::EMAIL_TEMPLATE_ACCESS,
      self::IMPORT_MEMBERS_EDIT => self::IMPORT_MEMBERS_ACCESS,
      self::IMPORT_PHOTOS_EDIT => self::IMPORT_PHOTOS_ACCESS,
      self::IMPORT_PRESENCES_EDIT => self::IMPORT_PRESENCES_ACCESS,
      default => null, // Already an ACCESS permission
    };
  }

  /**
   * Check if this is an EDIT permission
   */
  public function isEditPermission(): bool {
    return str_ends_with($this->value, '_EDIT');
  }

  /**
   * Check if this is an ACCESS permission
   */
  public function isAccessPermission(): bool {
    return str_ends_with($this->value, '_ACCESS');
  }

  /**
   * Get all feature names (without ACCESS/EDIT suffix)
   * @return string[]
   */
  public static function featureNames(): array {
    return ['EMAIL', 'EMAIL_TEMPLATE', 'IMPORT_MEMBERS', 'IMPORT_PHOTOS', 'IMPORT_PRESENCES'];
  }
}
