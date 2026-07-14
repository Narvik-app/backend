<?php

namespace App\Crypto;

/**
 * Stateless libsodium secretbox encrypt/decrypt routine, shared by
 * EncryptionService (DI-based) and EncryptedJsonType (DBAL types have no DI).
 *
 * Encrypted values are stored as base64(nonce . ciphertext).
 */
final class SecretBoxCipher {
  public static function decodeKey(string $encodedKey): string {
    $key = base64_decode($encodedKey, true);
    if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
      throw new \RuntimeException('ENCRYPTION_KEY must be a valid base64-encoded '.SODIUM_CRYPTO_SECRETBOX_KEYBYTES.'-byte key.');
    }
    return $key;
  }

  public static function encrypt(string $plaintext, string $key): string {
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    return base64_encode($nonce.sodium_crypto_secretbox($plaintext, $nonce, $key));
  }

  public static function decrypt(string $encrypted, string $key): string {
    $decoded = base64_decode($encrypted, true);
    if ($decoded === false) {
      throw new \RuntimeException('Invalid encrypted data: base64 decoding failed.');
    }

    if (strlen($decoded) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
      throw new \RuntimeException('Invalid encrypted data: too short.');
    }

    $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $ciphertext = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

    $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);
    if ($plaintext === false) {
      throw new \RuntimeException('Decryption failed. ENCRYPTION_KEY may be incorrect.');
    }

    return $plaintext;
  }
}
