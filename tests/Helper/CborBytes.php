<?php

namespace App\Tests\Helper;

/**
 * Marks a CBOR byte string (vs a text string) for SoftwareAuthenticator.
 */
final readonly class CborBytes {
  public function __construct(public string $data) {
  }
}
