<?php

namespace App\Service;

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class UuidService {
  public static function generateUuid(): UuidInterface {
    return UUID::uuid7();
  }

  public static function encodeToReadable(UuidInterface $uuid): string {
    return gmp_strval(gmp_init($uuid->getHex()->toString(), 16), 62);
  }

  public static function encodeUuidStringToReadable(string $uuid): string {
    return gmp_strval(gmp_init(str_replace("-", "", $uuid), 16), 62);
  }

  public static function fromReadable(string $hashId): UuidInterface {
    $decode =  array_reduce([20, 16, 12, 8], function ($uuid, $offset) {
      return substr_replace($uuid, '-', $offset, 0);
    }, str_pad(gmp_strval(gmp_init($hashId, 62), 16), 32, '0', STR_PAD_LEFT));

    return Uuid::fromString($decode);
  }
}
