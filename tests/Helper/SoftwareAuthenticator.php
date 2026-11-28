<?php

namespace App\Tests\Helper;

/**
 * Minimal software passkey (ES256, "none" attestation) to drive the WebAuthn ceremonies in tests.
 * It builds the same JSON as the browser (navigator.credentials.create()/get() serialized with toJSON()).
 */
final class SoftwareAuthenticator {
  private readonly \OpenSSLAsymmetricKey $privateKey;
  private readonly string $credentialId;
  private int $signCount = 0;
  private ?string $userHandle = null;

  public function __construct(
    private readonly string $origin = 'https://narvik.app',
  ) {
    $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    if (!$key) {
      throw new \RuntimeException('Unable to generate the test key');
    }
    $this->privateKey = $key;
    $this->credentialId = random_bytes(32);
  }

  public function getCredentialId(): string {
    return self::b64url($this->credentialId);
  }

  public function setSignCount(int $signCount): void {
    $this->signCount = $signCount;
  }

  /**
   * @param array $publicKey PublicKeyCredentialCreationOptions JSON returned by the API
   */
  public function create(array $publicKey): array {
    $this->userHandle = self::b64urlDecode($publicKey['user']['id']);

    $details = openssl_pkey_get_details($this->privateKey);
    $coseKey = self::cbor([
      1  => 2,  // kty: EC2
      3  => -7, // alg: ES256
      -1 => 1,  // crv: P-256
      -2 => new CborBytes(str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT)),
      -3 => new CborBytes(str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT)),
    ]);

    $authData = hash('sha256', $publicKey['rp']['id'], true)
      . chr(0x45) // UP | UV | AT
      . pack('N', $this->signCount)
      . str_repeat("\0", 16) // aaguid
      . pack('n', strlen($this->credentialId)) . $this->credentialId
      . $coseKey;

    $attestationObject = self::cbor([
      'fmt'      => 'none',
      'attStmt'  => [],
      'authData' => new CborBytes($authData),
    ]);

    return [
      'id'                     => $this->getCredentialId(),
      'rawId'                  => $this->getCredentialId(),
      'type'                   => 'public-key',
      'response'               => [
        'clientDataJSON'    => self::b64url($this->clientData('webauthn.create', $publicKey['challenge'])),
        'attestationObject' => self::b64url($attestationObject),
        'transports'        => ['internal'],
      ],
      'clientExtensionResults' => new \stdClass(),
    ];
  }

  /**
   * @param array $publicKey PublicKeyCredentialRequestOptions JSON returned by the API
   */
  public function get(array $publicKey, ?string $rpId = null): array {
    $this->signCount++;

    $authData = hash('sha256', $rpId ?? $publicKey['rpId'], true)
      . chr(0x05) // UP | UV
      . pack('N', $this->signCount);
    $clientData = $this->clientData('webauthn.get', $publicKey['challenge']);

    openssl_sign($authData . hash('sha256', $clientData, true), $signature, $this->privateKey, OPENSSL_ALGO_SHA256);

    return [
      'id'                     => $this->getCredentialId(),
      'rawId'                  => $this->getCredentialId(),
      'type'                   => 'public-key',
      'response'               => [
        'clientDataJSON'    => self::b64url($clientData),
        'authenticatorData' => self::b64url($authData),
        'signature'         => self::b64url($signature),
        'userHandle'        => $this->userHandle !== null ? self::b64url($this->userHandle) : null,
      ],
      'clientExtensionResults' => new \stdClass(),
    ];
  }

  /**
   * Pretend this authenticator holds a passkey for the given user handle (b64url), without registering it.
   */
  public function setUserHandle(string $userHandle): void {
    $this->userHandle = self::b64urlDecode($userHandle);
  }

  private function clientData(string $type, string $challenge): string {
    return json_encode([
      'type'        => $type,
      'challenge'   => $challenge,
      'origin'      => $this->origin,
      'crossOrigin' => false,
    ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
  }

  public static function b64url(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
  }

  public static function b64urlDecode(string $data): string {
    return base64_decode(strtr($data, '-_', '+/'), true);
  }

  /**
   * Tiny CBOR encoder, only what WebAuthn needs: ints, text strings, byte strings and maps.
   */
  private static function cbor(mixed $value): string {
    return match (true) {
      $value instanceof CborBytes => self::cborHead(2, strlen($value->data)) . $value->data,
      is_int($value) && $value >= 0 => self::cborHead(0, $value),
      is_int($value) => self::cborHead(1, -1 - $value),
      is_string($value) => self::cborHead(3, strlen($value)) . $value,
      is_array($value) => self::cborHead(5, count($value)) . implode('', array_map(
        fn($k, $v) => self::cbor($k) . self::cbor($v),
        array_keys($value),
        $value,
      )),
      default => throw new \InvalidArgumentException('Unsupported CBOR value'),
    };
  }

  private static function cborHead(int $major, int $length): string {
    return match (true) {
      $length < 24 => chr(($major << 5) | $length),
      $length < 0x100 => chr(($major << 5) | 24) . chr($length),
      $length < 0x10000 => chr(($major << 5) | 25) . pack('n', $length),
      default => chr(($major << 5) | 26) . pack('N', $length),
    };
  }
}
