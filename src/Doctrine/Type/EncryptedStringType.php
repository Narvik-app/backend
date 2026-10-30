<?php

namespace App\Doctrine\Type;

use App\Crypto\EncryptionKeyProvider;
use App\Crypto\SecretBoxCipher;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Custom Doctrine DBAL type that stores a PHP string as encrypted text.
 *
 * Encryption is transparent: entity getters/setters work with plain strings;
 * the ciphertext is only visible at the DB level. See EncryptedJsonType for
 * why the key is resolved through EncryptionKeyProvider rather than DI.
 *
 * Encryption is non-deterministic (random nonce per value), so encrypted
 * columns cannot be used in SQL equality/uniqueness/search predicates.
 *
 * Register in config/packages/doctrine.yaml:
 *   doctrine:
 *     dbal:
 *       types:
 *         encrypted_string: App\Doctrine\Type\EncryptedStringType
 */
class EncryptedStringType extends Type {
  public const NAME = 'encrypted_string';

  public function getSQLDeclaration(array $column, AbstractPlatform $platform): string {
    return 'TEXT';
  }

  #[\Override]
  public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string {
    if ($value === null) {
      return null;
    }

    return SecretBoxCipher::encrypt((string) $value, EncryptionKeyProvider::getKey());
  }

  #[\Override]
  public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?string {
    if ($value === null || $value === '') {
      return null;
    }

    return SecretBoxCipher::decrypt((string) $value, EncryptionKeyProvider::getKey());
  }

  public function requiresSQLCommentHint(AbstractPlatform $platform): bool {
    return true;
  }
}
