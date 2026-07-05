<?php

namespace App\Enum;

enum ThumbnailSize: string {
  case thumbnail = 'thumbnail';
  case medium = 'medium';

  /**
   * Maximum length (px) of the longest edge of the generated derivative.
   * The image is only ever downscaled, never upscaled.
   */
  public function maxEdge(): int {
    return match ($this) {
      self::thumbnail => 400,
      self::medium => 800,
    };
  }
}
