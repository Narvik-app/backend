<?php

namespace App\Tests\Unit\Service;

use App\Service\UuidService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class UuidServiceTest extends TestCase {
  /**
   * Readable ids are exposed in file URLs, so the encoding must never change.
   * Expected values were produced by the previous ramsey/uuid based implementation.
   */
  public static function readableProvider(): iterable {
    yield ['0193b683-b858-73ee-a9c7-41c84cd27fe2', '2yUpkHpzs5sKneIv4UdRC'];
    yield ['00000000-0000-7000-8000-000000000001', '2dwHTRTFRxWLTN'];
    yield ['ffffffff-ffff-7fff-bfff-ffffffffffff', '7n42DGM5QePXFZlRJ5QKHn'];
  }

  #[DataProvider('readableProvider')]
  public function testEncodeToReadableIsStable(string $uuid, string $readable): void {
    $this->assertSame($readable, UuidService::encodeToReadable(Uuid::fromString($uuid)));
  }

  #[DataProvider('readableProvider')]
  public function testFromReadableIsStable(string $uuid, string $readable): void {
    $this->assertSame($uuid, UuidService::fromReadable($readable)->toString());
  }

  public function testGenerateUuidIsV7AndRoundTrips(): void {
    $uuid = UuidService::generateUuid();

    $this->assertInstanceOf(\Symfony\Component\Uid\UuidV7::class, $uuid);
    $this->assertTrue($uuid->equals(UuidService::fromReadable(UuidService::encodeToReadable($uuid))));
  }
}
