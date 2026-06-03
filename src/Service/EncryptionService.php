<?php

namespace App\Service;

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
    #[Autowire('%env(ENCRYPTION_KEY)%')] string $encodedKey,
  ) {
    if (empty($encodedKey)) {
      throw new \RuntimeException('ENCRYPTION_KEY is not configured. Generate one with: php -r \'echo base64_encode(sodium_crypto_secretbox_keygen());\'');
    }

    $key = base64_decode($encodedKey, true);
    if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
      throw new \RuntimeException('ENCRYPTION_KEY must be a valid base64-encoded '.SODIUM_CRYPTO_SECRETBOX_KEYBYTES.'-byte key.');
    }

    $this->key = $key;
  }

  public function encrypt(string $plaintext): string {
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $this->key);
    return base64_encode($nonce.$ciphertext);
  }

  public function decrypt(string $encrypted): string {
    $decoded = base64_decode($encrypted, true);
    if ($decoded === false) {
      throw new \RuntimeException('Invalid encrypted data: base64 decoding failed.');
    }

    if (strlen($decoded) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
      throw new \RuntimeException('Invalid encrypted data: too short.');
    }

    $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $ciphertext = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

    $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $this->key);
    if ($plaintext === false) {
      throw new \RuntimeException('Decryption failed. The ENCRYPTION_KEY may be incorrect or data may be corrupted.');
    }

    return $plaintext;
  }
}
