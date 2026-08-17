<?php

namespace App\Doctrine\Type;

use App\Crypto\EncryptionKeyProvider;
use App\Crypto\SecretBoxCipher;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Custom Doctrine DBAL type that stores a PHP array as encrypted JSON text.
 *
 * Encryption is transparent: entity getters/setters work with plain PHP arrays;
 * the ciphertext is only visible at the DB level. The key is resolved through
 * EncryptionKeyProvider — DBAL types are instantiated by the type registry
 * without DI, so it can't be injected like in EncryptionService, but both
 * read the same env var through the same validation.
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
    return SecretBoxCipher::encrypt($json, EncryptionKeyProvider::getKey());
  }

  #[\Override]
  public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?array {
    if ($value === null || $value === '') {
      return null;
    }

    $json = SecretBoxCipher::decrypt((string) $value, EncryptionKeyProvider::getKey());
    return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
  }

  public function requiresSQLCommentHint(AbstractPlatform $platform): bool {
    return true;
  }
}
