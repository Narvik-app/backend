<?php

namespace App\Entity;

use App\Entity\Abstract\UuidEntity;
use App\Entity\Interface\TimestampEntityInterface;
use App\Entity\Trait\TimestampTrait;
use App\Enum\WebauthnChallengeType;
use App\Repository\WebauthnChallengeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Pending WebAuthn ceremony. The API is stateless (no session), so the options sent to the browser
 * are stored here until the browser answers. A challenge is single use and short-lived.
 */
#[ORM\Entity(repositoryClass: WebauthnChallengeRepository::class)]
class WebauthnChallenge extends UuidEntity implements TimestampEntityInterface {
  use TimestampTrait;

  public const string TTL = '+5 minutes';

  #[ORM\Column(type: Types::STRING, length: 20, enumType: WebauthnChallengeType::class)]
  private WebauthnChallengeType $type;

  /** Null for a login (discoverable credential: the user is unknown until the browser answers) */
  #[ORM\ManyToOne(targetEntity: User::class)]
  #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
  private ?User $user = null;

  /** JSON serialized PublicKeyCredentialCreationOptions / PublicKeyCredentialRequestOptions */
  #[ORM\Column(type: Types::TEXT)]
  private string $options;

  #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
  private \DateTimeImmutable $expireAt;

  public function __construct() {
    parent::__construct();
    $this->expireAt = new \DateTimeImmutable(self::TTL);
  }

  public function isExpired(): bool {
    return $this->expireAt < new \DateTimeImmutable();
  }

  public function getType(): WebauthnChallengeType {
    return $this->type;
  }

  public function setType(WebauthnChallengeType $type): static {
    $this->type = $type;
    return $this;
  }

  public function getUser(): ?User {
    return $this->user;
  }

  public function setUser(?User $user): static {
    $this->user = $user;
    return $this;
  }

  public function getOptions(): string {
    return $this->options;
  }

  public function setOptions(string $options): static {
    $this->options = $options;
    return $this;
  }

  public function getExpireAt(): \DateTimeImmutable {
    return $this->expireAt;
  }

  public function setExpireAt(\DateTimeImmutable $expireAt): static {
    $this->expireAt = $expireAt;
    return $this;
  }
}
