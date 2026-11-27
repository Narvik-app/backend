<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model;
use App\Controller\PasskeyLoginOptions;
use App\Controller\SelfPasskeyRegister;
use App\Controller\SelfPasskeyRegisterOptions;
use App\Entity\Abstract\UuidEntity;
use App\Entity\Interface\TimestampEntityInterface;
use App\Entity\Trait\TimestampTrait;
use App\Repository\WebauthnCredentialRepository;
use App\State\SelfPasskeyProvider;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A passkey (WebAuthn discoverable credential) registered by a user to log in without password.
 */
#[ORM\Entity(repositoryClass: WebauthnCredentialRepository::class)]
#[ApiResource(
  operations: [
    new GetCollection(
      uriTemplate: '/self/passkeys',
      paginationEnabled: false,
      openapi: new Model\Operation(summary: 'List the passkeys of the logged user'),
      provider: SelfPasskeyProvider::class,
    ),
    new Post(
      uriTemplate: '/self/passkeys/-/options',
      controller: SelfPasskeyRegisterOptions::class,
      openapi: new Model\Operation(
        summary: 'Start a passkey registration. Return the challenge id and the PublicKeyCredentialCreationOptions to give to navigator.credentials.create()',
      ),
      read: false,
      deserialize: false,
      write: false,
      serialize: false,
    ),
    new Post(
      uriTemplate: '/self/passkeys',
      controller: SelfPasskeyRegister::class,
      openapi: new Model\Operation(
        summary: 'Finish a passkey registration with the browser attestation response',
        requestBody: new Model\RequestBody(
          content: new \ArrayObject([
            'application/json' => [
              'schema' => [
                'type'       => 'object',
                'properties' => [
                  'challengeId' => ['type' => 'string'],
                  'credential'  => ['type' => 'object'],
                  'name'        => ['type' => 'string'],
                ],
                'required' => ['challengeId', 'credential'],
              ],
            ],
          ]),
        ),
      ),
      read: false,
      deserialize: false,
      write: false,
    ),
    new Get(
      uriTemplate: '/self/passkeys/{uuid}',
      security: 'object.getUser() == user',
    ),
    new Patch(
      uriTemplate: '/self/passkeys/{uuid}',
      security: 'object.getUser() == user',
    ),
    new Delete(
      uriTemplate: '/self/passkeys/{uuid}',
      security: 'object.getUser() == user',
    ),
    new Post(
      uriTemplate: '/public/passkeys/-/login-options',
      controller: PasskeyLoginOptions::class,
      openapi: new Model\Operation(
        summary: 'Start a passkey login. Return the challenge id and the PublicKeyCredentialRequestOptions to give to navigator.credentials.get(). Then call POST /token with grant_type=urn:narvik:grant:passkey',
      ),
      read: false,
      deserialize: false,
      write: false,
      serialize: false,
    ),
  ],
  normalizationContext: ['groups' => ['passkey-read']],
  denormalizationContext: ['groups' => ['passkey-write']],
)]
class WebauthnCredential extends UuidEntity implements TimestampEntityInterface {
  use TimestampTrait;

  #[ORM\ManyToOne(targetEntity: User::class)]
  #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
  private ?User $user = null;

  /** Base64url (unpadded) of the raw credential id, used to find the credential on login */
  #[ORM\Column(length: 1400, unique: true)] // Raw ids are at most 1023 bytes (WebAuthn spec)
  private string $credentialId;

  /** Normalized Webauthn\CredentialRecord (public key, counter, transports, user handle...) */
  #[ORM\Column(type: Types::JSON)]
  private array $record = [];

  #[ORM\Column(length: 100)]
  #[Groups(['passkey-read', 'passkey-write'])]
  #[Assert\NotBlank]
  #[Assert\Length(max: 100)]
  private string $name;

  #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
  #[Groups(['passkey-read'])]
  private ?\DateTimeImmutable $lastUsedAt = null;

  public function getUser(): ?User {
    return $this->user;
  }

  public function setUser(User $user): static {
    $this->user = $user;
    return $this;
  }

  public function getCredentialId(): string {
    return $this->credentialId;
  }

  public function setCredentialId(string $credentialId): static {
    $this->credentialId = $credentialId;
    return $this;
  }

  public function getRecord(): array {
    return $this->record;
  }

  public function setRecord(array $record): static {
    $this->record = $record;
    return $this;
  }

  public function getName(): string {
    return $this->name;
  }

  public function setName(string $name): static {
    $this->name = mb_trim($name);
    return $this;
  }

  public function getLastUsedAt(): ?\DateTimeImmutable {
    return $this->lastUsedAt;
  }

  public function setLastUsedAt(?\DateTimeImmutable $lastUsedAt): static {
    $this->lastUsedAt = $lastUsedAt;
    return $this;
  }
}
