<?php

namespace App\Tests\e2e;

use App\Tests\Constraint\ResponseIsClientError;
use Symfony\Contracts\HttpClient\ResponseInterface;

trait CustomApiTestAssertionsTrait {
  public static function assertResponseIsClientError(string $message = '', bool $verbose = true): void {
    self::assertThatForResponse(new ResponseIsClientError($verbose), $message);
  }

  public static function assertJsonNotHasKey(string $key, ResponseInterface $response): void {
    static::assertArrayNotHasKey($key, $response->toArray(false));
  }

  public static function assertJsonHasKey(string $key, ResponseInterface $response): void {
    static::assertArrayHasKey($key, $response->toArray(false));
  }
}
