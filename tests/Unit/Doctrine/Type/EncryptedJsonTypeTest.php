<?php

namespace App\Tests\Unit\Doctrine\Type;

use App\Doctrine\Type\EncryptedJsonType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use PHPUnit\Framework\TestCase;

/**
 * ENCRYPTION_KEY is set in .env.test, so EncryptionKeyProvider (used
 * internally by EncryptedJsonType) resolves without extra setup here.
 */
class EncryptedJsonTypeTest extends TestCase {
  private function type(): EncryptedJsonType {
    return new EncryptedJsonType();
  }

  private function platform(): AbstractPlatform {
    return $this->createStub(AbstractPlatform::class);
  }

  public function testConvertToDatabaseValueEncryptsAsJson(): void {
    $value = ['apiKey' => 'sk_test_123', 'merchantCode' => 'ABC'];
    $stored = $this->type()->convertToDatabaseValue($value, $this->platform());

    $this->assertIsString($stored);
    $this->assertStringNotContainsString('sk_test_123', $stored);
  }

  public function testRoundTripsThroughDatabaseValue(): void {
    $value = ['apiKey' => 'sk_test_123', 'nested' => ['a', 'b']];
    $type = $this->type();
    $platform = $this->platform();

    $stored = $type->convertToDatabaseValue($value, $platform);
    $restored = $type->convertToPHPValue($stored, $platform);

    $this->assertSame($value, $restored);
  }

  public function testNullPassesThroughBothWays(): void {
    $type = $this->type();
    $platform = $this->platform();

    $this->assertNull($type->convertToDatabaseValue(null, $platform));
    $this->assertNull($type->convertToPHPValue(null, $platform));
    $this->assertNull($type->convertToPHPValue('', $platform));
  }

  public function testConvertToPHPValueRejectsTamperedCiphertext(): void {
    $type = $this->type();
    $platform = $this->platform();

    $stored = $type->convertToDatabaseValue(['a' => 1], $platform);
    $tampered = substr($stored, 0, -4).'abcd';

    $this->expectException(\RuntimeException::class);
    $type->convertToPHPValue($tampered, $platform);
  }
}
