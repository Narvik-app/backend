<?php

namespace App\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Custom Doctrine DBAL type that stores a PHP array as encrypted JSON text.
 *
 * Encryption is transparent: entity getters/setters work with plain PHP arrays;
 * the ciphertext is only visible at the DB level. Key is read from the ENCRYPTION_KEY
 * environment variable (base64-encoded 32-byte libsodium secretbox key).
 *
 * Register in config/packages/doctrine.yaml:
 *   doctrine:
 *     dbal:
 *       types:
 *         encrypted_json: App\Doctrine\Type\EncryptedJsonType
 */
class EncryptedJsonType extends Type {
  public const NAME = 'encrypted_json';

  public function getSQLDeclaration(array $column, AbstractPlatform $platform): string {
    return 'TEXT';
  }

  #[\Override]
  public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string {
    if ($value === null) {
      return null;
    }

    $json = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    return $this->getEncryptor()->encrypt($json);
  }

  #[\Override]
  public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?array {
    if ($value === null || $value === '') {
      return null;
    }

    $json = $this->getEncryptor()->decrypt((string) $value);
    return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
  }

  public function requiresSQLCommentHint(AbstractPlatform $platform): bool {
    return true;
  }

  private function getEncryptor(): Encryptor {
    /** @var Encryptor|null $encryptor */
    static $encryptor = null;
    if ($encryptor === null) {
      $encodedKey = (string) (getenv('ENCRYPTION_KEY') ?: ($_ENV['ENCRYPTION_KEY'] ?? $_SERVER['ENCRYPTION_KEY'] ?? ''));
      if (empty($encodedKey)) {
        throw new \RuntimeException(
          'ENCRYPTION_KEY environment variable is required for encrypted fields. '.
          'Generate one with: php -r \'echo base64_encode(sodium_crypto_secretbox_keygen());\'',
        );
      }
      $encryptor = new Encryptor($encodedKey);
    }
    return $encryptor;
  }
}

/**
 * Minimal encryptor for use inside the DBAL type (no DI available at this layer).
 * Uses the same algorithm as EncryptionService for cross-service compatibility.
 *
 * @internal
 */
class Encryptor {
  private readonly string $key;

  public function __construct(string $encodedKey) {
    $key = base64_decode($encodedKey, true);
    if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
      throw new \RuntimeException('ENCRYPTION_KEY must be a valid base64-encoded '.SODIUM_CRYPTO_SECRETBOX_KEYBYTES.'-byte key.');
    }
    $this->key = $key;
  }

  public function encrypt(string $plaintext): string {
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    return base64_encode($nonce.sodium_crypto_secretbox($plaintext, $nonce, $this->key));
  }

  public function decrypt(string $encrypted): string {
    $decoded = base64_decode($encrypted, true);
    if ($decoded === false || strlen($decoded) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
      throw new \RuntimeException('Invalid encrypted data.');
    }
    $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $plaintext = sodium_crypto_secretbox_open(substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, $this->key);
    if ($plaintext === false) {
      throw new \RuntimeException('Decryption failed. ENCRYPTION_KEY may be incorrect.');
    }
    return $plaintext;
  }
}
