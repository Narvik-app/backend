<?php

namespace App\Tests\Unit\Crypto;

use App\Crypto\SecretBoxCipher;
use PHPUnit\Framework\TestCase;

class SecretBoxCipherTest extends TestCase {
  private function key(): string {
    return sodium_crypto_secretbox_keygen();
  }

  public function testEncryptThenDecryptRoundTrips(): void {
    $key = $this->key();
    $encrypted = SecretBoxCipher::encrypt('sk_test_secret', $key);

    $this->assertNotSame('sk_test_secret', $encrypted);
    $this->assertSame('sk_test_secret', SecretBoxCipher::decrypt($encrypted, $key));
  }

  public function testEncryptIsNotDeterministic(): void {
    $key = $this->key();

    $this->assertNotSame(
      SecretBoxCipher::encrypt('same-plaintext', $key),
      SecretBoxCipher::encrypt('same-plaintext', $key),
    );
  }

  public function testDecryptWithWrongKeyThrows(): void {
    $encrypted = SecretBoxCipher::encrypt('sk_test_secret', $this->key());

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Decryption failed');
    SecretBoxCipher::decrypt($encrypted, $this->key());
  }

  public function testDecryptTamperedCiphertextThrows(): void {
    $key = $this->key();
    $encrypted = SecretBoxCipher::encrypt('sk_test_secret', $key);

    $tampered = substr($encrypted, 0, -4).'abcd';

    $this->expectException(\RuntimeException::class);
    SecretBoxCipher::decrypt($tampered, $key);
  }

  public function testDecryptInvalidBase64Throws(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('base64 decoding failed');
    SecretBoxCipher::decrypt('not-valid-base64-!!!', $this->key());
  }

  public function testDecryptTooShortThrows(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('too short');
    SecretBoxCipher::decrypt(base64_encode('short'), $this->key());
  }

  public function testDecodeKeyRejectsInvalidLength(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('ENCRYPTION_KEY must be a valid base64-encoded');
    SecretBoxCipher::decodeKey(base64_encode('too-short'));
  }

  public function testDecodeKeyAcceptsValidKey(): void {
    $encoded = base64_encode($this->key());
    $decoded = SecretBoxCipher::decodeKey($encoded);

    $this->assertSame(SODIUM_CRYPTO_SECRETBOX_KEYBYTES, strlen($decoded));
  }
}
