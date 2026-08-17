<?php

namespace App\Crypto;

/**
 * Single source of truth for the application's symmetric encryption key.
 *
 * Both EncryptionService (DI-based, used by regular services/controllers) and
 * the encrypted Doctrine DBAL types (instantiated by the type registry without
 * DI, so #[Autowire(env:)] / ParameterBagInterface aren't reachable there)
 * read the key through this class, so the env var name, its validation and
 * its error message are defined in exactly one place.
 *
 * $_ENV/$_SERVER only, no getenv(): Symfony's Dotenv component populates
 * both superglobals, and Symfony's own docs recommend against getenv() here
 * — it's process-global mutable state, not thread-safe, and can go stale
 * across requests in long-running workers (PHP-FPM, Messenger, Swoole/RoadRunner).
 */
final class EncryptionKeyProvider {
  private const ENV_VAR = 'ENCRYPTION_KEY';

  /** @var string|null */
  private static $key = null;

  public static function getKey(): string {
    if (self::$key === null) {
      $encodedKey = (string) ($_ENV[self::ENV_VAR] ?? $_SERVER[self::ENV_VAR] ?? '');
      self::$key = self::decodeKey($encodedKey);
    }

    return self::$key;
  }

  /**
   * Decode an explicitly provided key (e.g. injected via DI) instead of
   * reading the environment, still going through the shared validation.
   */
  public static function decodeKey(string $encodedKey): string {
    if (empty($encodedKey)) {
      throw new \RuntimeException(
        self::ENV_VAR.' is not configured. Generate one with: '.
        'php -r \'echo base64_encode(sodium_crypto_secretbox_keygen());\'',
      );
    }

    return SecretBoxCipher::decodeKey($encodedKey);
  }
}
