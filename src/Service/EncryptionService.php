<?php

namespace App\Service;

use App\Crypto\EncryptionKeyProvider;
use App\Crypto\SecretBoxCipher;

/**
 * Generic symmetric encryption service using libsodium secretbox.
 *
 * Encrypted values are stored as base64(nonce . ciphertext).
 */
class EncryptionService {
  private readonly string $key;

  public function __construct() {
    $this->key = EncryptionKeyProvider::getKey();
  }

  public function encrypt(string $plaintext): string {
    return SecretBoxCipher::encrypt($plaintext, $this->key);
  }

  public function decrypt(string $encrypted): string {
    return SecretBoxCipher::decrypt($encrypted, $this->key);
  }
}
