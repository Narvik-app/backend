<?php

namespace App\Tests\Unit\Crypto;

use App\Crypto\EncryptionKeyProvider;
use PHPUnit\Framework\TestCase;

class EncryptionKeyProviderTest extends TestCase {
  public function testGetKeyReadsTheConfiguredEnvKey(): void {
    // ENCRYPTION_KEY is set in .env.test; this proves the provider resolves
    // the same env var EncryptionService and EncryptedJsonType share.
    $key = EncryptionKeyProvider::getKey();

    $this->assertSame(SODIUM_CRYPTO_SECRETBOX_KEYBYTES, strlen($key));
  }

  public function testDecodeKeyRejectsEmptyKey(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessageIsOrContains('ENCRYPTION_KEY is not configured');
    EncryptionKeyProvider::decodeKey('');
  }

  public function testDecodeKeyRejectsInvalidKey(): void {
    $this->expectException(\RuntimeException::class);
    EncryptionKeyProvider::decodeKey(base64_encode('too-short'));
  }

  public function testDecodeKeyAcceptsExplicitValidKey(): void {
    $encoded = base64_encode(sodium_crypto_secretbox_keygen());
    $decoded = EncryptionKeyProvider::decodeKey($encoded);

    $this->assertSame(SODIUM_CRYPTO_SECRETBOX_KEYBYTES, strlen($decoded));
  }
}
