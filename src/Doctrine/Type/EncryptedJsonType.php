<?php

namespace App\Doctrine\Type;

use App\Crypto\SecretBoxCipher;
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
    return SecretBoxCipher::encrypt($json, $this->getKey());
  }

  #[\Override]
  public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?array {
    if ($value === null || $value === '') {
      return null;
    }

    $json = SecretBoxCipher::decrypt((string) $value, $this->getKey());
    return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
  }

  public function requiresSQLCommentHint(AbstractPlatform $platform): bool {
    return true;
  }

  /**
   * DBAL types are instantiated by the type registry without constructor
   * arguments, so the key is read from the environment directly instead of
   * being injected like in EncryptionService.
   */
  private function getKey(): string {
    /** @var string|null $key */
    static $key = null;
    if ($key === null) {
      $encodedKey = (string) (getenv('ENCRYPTION_KEY') ?: ($_ENV['ENCRYPTION_KEY'] ?? $_SERVER['ENCRYPTION_KEY'] ?? ''));
      if (empty($encodedKey)) {
        throw new \RuntimeException(
          'ENCRYPTION_KEY environment variable is required for encrypted fields. '.
          'Generate one with: php -r \'echo base64_encode(sodium_crypto_secretbox_keygen());\'',
        );
      }
      $key = SecretBoxCipher::decodeKey($encodedKey);
    }
    return $key;
  }
}
