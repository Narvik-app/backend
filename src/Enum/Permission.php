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

  // Sale permissions
  case SALE_NEW = 'SALE_NEW'; // Grants ability to make sales, implies SALE_HISTORY_ACCESS and SALE_INVENTORY_ACCESS

  case SALE_HISTORY_ACCESS = 'SALE_HISTORY_ACCESS';
  case SALE_HISTORY_EDIT = 'SALE_HISTORY_EDIT';

  case SALE_INVENTORY_ACCESS = 'SALE_INVENTORY_ACCESS';
  case SALE_INVENTORY_EDIT = 'SALE_INVENTORY_EDIT';

  case SALE_CATEGORIES_ACCESS = 'SALE_CATEGORIES_ACCESS';
  case SALE_CATEGORIES_EDIT = 'SALE_CATEGORIES_EDIT';

  case SALE_PAYMENT_MODES_ACCESS = 'SALE_PAYMENT_MODES_ACCESS';
  case SALE_PAYMENT_MODES_EDIT = 'SALE_PAYMENT_MODES_EDIT';

  case SALE_IMPORT_ACCESS = 'SALE_IMPORT_ACCESS';
  case SALE_IMPORT_EDIT = 'SALE_IMPORT_EDIT';

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
    if ($this->isAccessPermission()) {
      return $this; // Already an ACCESS permission
    }

    // Derive ACCESS permission by replacing _EDIT suffix with _ACCESS
    $accessValue = str_replace('_EDIT', '_ACCESS', $this->value);
    return self::tryFrom($accessValue);
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
   * Get implied permissions
   * @return Permission[]
   */
  public function getImpliedPermissions(): array {
    $implied = [];

    // Hierarchy: EDIT implies ACCESS
    if ($this->isEditPermission()) {
      $access = $this->getAccessPermission();
      if ($access) {
        $implied[] = $access;
      }
    }

    // Special cases
    // SALE_NEW implies SALE_HISTORY and SALE_INVENTORY access
    if ($this === self::SALE_NEW) {
      $implied[] = self::SALE_HISTORY_ACCESS;
      $implied[] = self::SALE_INVENTORY_ACCESS;
    }

    return $implied;
  }
}
