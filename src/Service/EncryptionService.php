<?php

namespace App\Service;

use App\Crypto\SecretBoxCipher;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Generic symmetric encryption service using libsodium secretbox.
 *
 * Encrypted values are stored as base64(nonce . ciphertext).
 * The key must be a base64-encoded 32-byte value set in the ENCRYPTION_KEY env var.
 *
 * Generate a key:
 *   php -r 'echo base64_encode(sodium_crypto_secretbox_keygen());'
 */
class EncryptionService {
  private readonly string $key;

  public function __construct(
    #[Autowire(env: 'ENCRYPTION_KEY')] string $encodedKey,
  ) {
    if (empty($encodedKey)) {
      throw new \RuntimeException('ENCRYPTION_KEY is not configured. Generate one with: php -r \'echo base64_encode(sodium_crypto_secretbox_keygen());\'');
    }

    $this->key = SecretBoxCipher::decodeKey($encodedKey);
  }

  public function encrypt(string $plaintext): string {
    return SecretBoxCipher::encrypt($plaintext, $this->key);
  }

  public function decrypt(string $encrypted): string {
    return SecretBoxCipher::decrypt($encrypted, $this->key);
  }
}
