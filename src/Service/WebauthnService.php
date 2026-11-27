<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\WebauthnChallenge;
use App\Entity\WebauthnCredential;
use App\Enum\WebauthnChallengeType;
use App\Exception\InvalidPasskeyException;
use App\Exception\PasskeyAlreadyRegisteredException;
use App\Repository\WebauthnChallengeRepository;
use App\Repository\WebauthnCredentialRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\CredentialRecord;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;

/**
 * Passkey (WebAuthn) ceremonies. Only discoverable credentials with user verification are used, so a
 * passkey is enough on its own to log in (no email, no password).
 *
 * The browser talks to the backend through the frontend, so the WebAuthn origin is the frontend URL
 * and the Relying Party ID defaults to its host. With several allowed origins (i.e. localhost and a
 * tunnel in dev), the RP ID follows the browser Origin of each ceremony, and a passkey only works on
 * the domain it was created on.
 */
class WebauthnService {
  private const int TIMEOUT_MS = 300_000;
  // COSE algorithm ids supported by the default CeremonyStepManagerFactory
  private const int COSE_ES256 = -7;
  private const int COSE_RS256 = -257;

  private readonly SerializerInterface&NormalizerInterface&DenormalizerInterface $serializer;
  private readonly CeremonyStepManagerFactory $ceremonyFactory;
  /** Explicit WEBAUTHN_RP_ID, used for every origin */
  private readonly ?string $forcedRpId;
  /** FRONTEND_URL host, when the browser origin is unknown */
  private readonly string $defaultRpId;
  /** @var list<string> */
  private readonly array $allowedOrigins;

  public function __construct(
    private readonly EntityManagerInterface $em,
    private readonly WebauthnChallengeRepository $challengeRepository,
    private readonly WebauthnCredentialRepository $credentialRepository,
    private readonly LoggerInterface $logger,
    private readonly RequestStack $requestStack,
    #[Autowire(param: 'app.frontend_url')]
    string $frontendUrl,
    #[Autowire(param: 'app.webauthn_rp_id')]
    ?string $rpId = null,
    #[Autowire(param: 'app.webauthn_allowed_origins')]
    ?string $allowedOrigins = null,
  ) {
    $parsed = parse_url($frontendUrl);
    if (!is_array($parsed) || !isset($parsed['scheme'], $parsed['host'])) {
      throw new \LogicException("FRONTEND_URL must be an absolute URL to use passkeys.");
    }
    $origin = "{$parsed['scheme']}://{$parsed['host']}" . (isset($parsed['port']) ? ":{$parsed['port']}" : '');
    $this->forcedRpId = $rpId ?: null;
    $this->defaultRpId = $parsed['host'];
    $origins = array_values(array_filter(array_map(trim(...), explode(',', (string) $allowedOrigins))));
    $this->allowedOrigins = $origins ?: [$origin];

    $attestationManager = new AttestationStatementSupportManager([new NoneAttestationStatementSupport()]);
    /** @var SerializerInterface&NormalizerInterface&DenormalizerInterface $serializer */
    $serializer = new WebauthnSerializerFactory($attestationManager)->create();
    $this->serializer = $serializer;

    $this->ceremonyFactory = new CeremonyStepManagerFactory();
    $this->ceremonyFactory->setAttestationStatementSupportManager($attestationManager);
    $this->ceremonyFactory->setAllowedOrigins($this->allowedOrigins);
  }

  /**
   * Start a registration for a logged user.
   *
   * @return array{challengeId: string, publicKey: array}
   */
  public function createRegistrationOptions(User $user): array {
    $excludeCredentials = array_map(
      fn(WebauthnCredential $c) => $this->toCredentialRecord($c)->getPublicKeyCredentialDescriptor(),
      $this->credentialRepository->findByUser($user),
    );

    $options = PublicKeyCredentialCreationOptions::create(
      // The name is deprecated by the spec, browsers show the RP ID
      rp: PublicKeyCredentialRpEntity::create('', $this->resolveRpId()),
      user: PublicKeyCredentialUserEntity::create(
        name: $user->getEmail(),
        id: self::getUserHandle($user),
        displayName: trim((string) $user->getFullName()) ?: $user->getEmail(),
      ),
      challenge: random_bytes(32),
      pubKeyCredParams: [
        PublicKeyCredentialParameters::createPk(self::COSE_ES256),
        PublicKeyCredentialParameters::createPk(self::COSE_RS256),
      ],
      authenticatorSelection: AuthenticatorSelectionCriteria::create(
        userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
        residentKey: AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED,
      ),
      attestation: PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
      excludeCredentials: $excludeCredentials,
      timeout: self::TIMEOUT_MS,
    );

    return $this->storeChallenge(WebauthnChallengeType::creation, $options, $user);
  }

  /**
   * Validate the browser attestation and save the new passkey.
   */
  public function verifyRegistration(User $user, string $challengeId, array|string $credential, ?string $name): WebauthnCredential {
    $challenge = $this->consumeChallenge($challengeId, WebauthnChallengeType::creation);
    if ($challenge->getUser() !== $user) {
      throw new InvalidPasskeyException('Invalid challenge.');
    }

    /** @var PublicKeyCredentialCreationOptions $options */
    $options = $this->serializer->deserialize($challenge->getOptions(), PublicKeyCredentialCreationOptions::class, 'json');
    $publicKeyCredential = $this->loadPublicKeyCredential($credential);
    if (!$publicKeyCredential->response instanceof AuthenticatorAttestationResponse) {
      throw new InvalidPasskeyException('Invalid passkey response.');
    }

    try {
      $record = AuthenticatorAttestationResponseValidator::create($this->ceremonyFactory->creationCeremony())
        ->check($publicKeyCredential->response, $options, $options->rp->id);
    } catch (\Throwable $e) {
      $this->logger->info('Passkey registration refused', ['error' => $e->getMessage()]);
      throw new InvalidPasskeyException('Invalid passkey response.');
    }

    $credentialId = self::base64Url($record->publicKeyCredentialId);
    if ($this->credentialRepository->findOneByCredentialId($credentialId)) {
      throw new PasskeyAlreadyRegisteredException('This passkey is already registered.');
    }

    $passkey = new WebauthnCredential()
      ->setUser($user)
      ->setCredentialId($credentialId)
      ->setRecord($this->serializer->normalize($record))
      ->setName(mb_trim((string) $name) ?: 'Passkey');

    $this->em->persist($passkey);
    $this->em->flush();

    return $passkey;
  }

  /**
   * Start a passwordless login. No allowCredentials: the authenticator lets the user pick an account.
   *
   * @return array{challengeId: string, publicKey: array}
   */
  public function createLoginOptions(): array {
    $options = PublicKeyCredentialRequestOptions::create(
      challenge: random_bytes(32),
      rpId: $this->resolveRpId(),
      userVerification: PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_REQUIRED,
      timeout: self::TIMEOUT_MS,
    );

    return $this->storeChallenge(WebauthnChallengeType::request, $options);
  }

  /**
   * Validate the browser assertion and return the owner of the passkey.
   * Every failure returns the same generic error, to not leak which credentials exist.
   */
  public function verifyLogin(string $challengeId, array|string $credential): User {
    $challenge = $this->consumeChallenge($challengeId, WebauthnChallengeType::request);

    /** @var PublicKeyCredentialRequestOptions $options */
    $options = $this->serializer->deserialize($challenge->getOptions(), PublicKeyCredentialRequestOptions::class, 'json');
    $publicKeyCredential = $this->loadPublicKeyCredential($credential);
    $response = $publicKeyCredential->response;
    if (!$response instanceof AuthenticatorAssertionResponse) {
      throw new InvalidPasskeyException('Invalid passkey response.');
    }

    $passkey = $this->credentialRepository->findOneByCredentialId(self::base64Url($publicKeyCredential->rawId));
    $user = $passkey?->getUser();
    // Discoverable credentials must return the user handle, and it must match the passkey owner
    if (!$passkey || !$user || $response->userHandle === null || !hash_equals(self::getUserHandle($user), $response->userHandle)) {
      throw new InvalidPasskeyException('Invalid passkey.');
    }

    try {
      $record = AuthenticatorAssertionResponseValidator::create($this->ceremonyFactory->requestCeremony())
        ->check($this->toCredentialRecord($passkey), $response, $options, $options->rpId, $response->userHandle);
    } catch (\Throwable $e) {
      // Also covers a sign counter going backward (cloned authenticator)
      $this->logger->warning('Passkey login refused', ['passkey' => $passkey->getUuid()?->toString(), 'error' => $e->getMessage()]);
      throw new InvalidPasskeyException('Invalid passkey.');
    }

    $passkey
      ->setRecord($this->serializer->normalize($record)) // Updated counter & backup flags
      ->setLastUsedAt(new \DateTimeImmutable());
    $this->em->flush();

    return $user;
  }

  /**
   * Opaque, stable WebAuthn user id. We use the user uuid (no personal data).
   */
  public static function getUserHandle(User $user): string {
    return $user->getUuid()->toRfc4122();
  }

  /**
   * RP ID of a new ceremony: the host of the browser Origin when it's an allowed origin. It's stored in
   * the challenge options, so the verification uses the same one. A forged Origin can only pick another
   * allowed origin, and the signed origin is still checked against them.
   */
  private function resolveRpId(): string {
    if ($this->forcedRpId) {
      return $this->forcedRpId;
    }

    $origin = $this->requestStack->getCurrentRequest()?->headers->get('Origin');
    if ($origin && in_array($origin, $this->allowedOrigins, true)) {
      $host = parse_url($origin, PHP_URL_HOST);
      if (is_string($host) && $host !== '') {
        return $host;
      }
    }

    return $this->defaultRpId;
  }

  private static function base64Url(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
  }

  private function toCredentialRecord(WebauthnCredential $passkey): CredentialRecord {
    return $this->serializer->denormalize($passkey->getRecord(), CredentialRecord::class);
  }

  private function loadPublicKeyCredential(array|string $credential): PublicKeyCredential {
    try {
      $json = is_array($credential) ? json_encode($credential, JSON_THROW_ON_ERROR) : $credential;
      /** @var PublicKeyCredential */
      return $this->serializer->deserialize($json, PublicKeyCredential::class, 'json');
    } catch (\Throwable) {
      throw new InvalidPasskeyException('Invalid passkey response.');
    }
  }

  /**
   * @return array{challengeId: string, publicKey: array}
   */
  private function storeChallenge(WebauthnChallengeType $type, PublicKeyCredentialCreationOptions|PublicKeyCredentialRequestOptions $options, ?User $user = null): array {
    $json = $this->serializer->serialize($options, 'json', [
      AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
      JsonEncode::OPTIONS => JSON_THROW_ON_ERROR,
    ]);

    $challenge = new WebauthnChallenge()
      ->setType($type)
      ->setUser($user)
      ->setOptions($json);

    $this->em->persist($challenge);
    $this->em->flush();

    return [
      'challengeId' => $challenge->getUuid()->toString(),
      'publicKey'   => json_decode($json, true, flags: JSON_THROW_ON_ERROR),
    ];
  }

  /**
   * Load a pending challenge and delete it right away: a challenge can only be answered once.
   */
  private function consumeChallenge(string $challengeId, WebauthnChallengeType $type): WebauthnChallenge {
    $challenge = $this->challengeRepository->findOneByUuid($challengeId);
    if (!$challenge || $challenge->getType() !== $type) {
      throw new InvalidPasskeyException('Invalid challenge.');
    }

    // Atomic delete (not remove/flush: Doctrine doesn't check the affected rows), so two concurrent
    // requests answering the same challenge can't both go through
    $this->em->detach($challenge);
    if (!$this->challengeRepository->deleteOne($challenge)) {
      throw new InvalidPasskeyException('Invalid challenge.');
    }

    if ($challenge->isExpired()) {
      throw new InvalidPasskeyException('Challenge expired.');
    }

    return $challenge;
  }
}
