<?php

namespace App\Service;

use Symfony\Component\Uid\Uuid;

class UuidService {
  public static function generateUuid(): Uuid {
    return Uuid::v7();
  }

  public static function encodeToReadable(Uuid $uuid): string {
    return gmp_strval(gmp_init(bin2hex($uuid->toBinary()), 16), 62);
  }

  public static function fromReadable(string $hashId): Uuid {
    $decode =  array_reduce([20, 16, 12, 8], function ($uuid, $offset) {
      return substr_replace($uuid, '-', $offset, 0);
    }, str_pad(gmp_strval(gmp_init($hashId, 62), 16), 32, '0', STR_PAD_LEFT));

    return Uuid::fromString($decode);
  }
}
