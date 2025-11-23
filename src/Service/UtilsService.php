<?php

namespace App\Service;

class UtilsService {

  /**
   * Format a string boolean representation to a PHP boolean value.
   *
   * @param string|int|bool|null $value
   * @param bool $default
   * @return bool
   */
  public static function toBoolean(string|int|bool|null $value, bool $default = false): bool
  {
    if (is_bool($value)) {
      return $value;
    }

    return match (strtolower((string) $value)) {
      'true', '1' => true,
      'false', '0' => false,
      default => $default,
    };
  }

  public static function convertStringToDbDecimal(?string $string): ?string
  {
    if (!is_numeric($string) && empty($string)) {
      return null;
    }
    return filter_var(str_replace(',', '.', $string), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
  }

  public function generateRandomToken(int $length): string
  {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz-';
    $charLength = strlen($characters) - 1;
    $result = '';
    for ($i = 0; $i < $length; $i++) {
      $result .= $characters[mt_rand(0, $charLength)];
    }
    return $result;
  }
}
