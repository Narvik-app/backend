<?php

namespace App\Entity;

use App\Entity\Interface\TimestampEntityInterface;
use App\Entity\Trait\TimestampTrait;
use App\Enum\UserSecurityCodeTrigger;
use App\Repository\UserSecurityCodeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserSecurityCodeRepository::class)]
class UserSecurityCode implements TimestampEntityInterface {
  use TimestampTrait;

  #[ORM\Id]
  #[ORM\GeneratedValue(strategy: 'SEQUENCE')]
  #[ORM\Column]
  private ?int $id = null;

  #[ORM\Column(length: 10)]
  private string $code;

  #[ORM\ManyToOne(targetEntity: User::class)]
  #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
  private ?User $user = null;

  #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, enumType: UserSecurityCodeTrigger::class)]
  private ?UserSecurityCodeTrigger $trigger = null;

  #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
  private \DateTimeImmutable $expireAt;

  public function __construct() {
    $this->setCode(substr(str_shuffle(strtoupper(bin2hex(random_bytes(10)))), 0, 6));
    $this->setExpireAt(new \DateTimeImmutable('+ 10 minutes'));
  }

  public function getId(): ?int {
    return $this->id;
  }

  public function getCode(): string {
    return $this->code;
  }

  public function setCode(string $code): static {
    $this->code = $code;
    return $this;
  }

  public function getUser(): ?User {
    return $this->user;
  }

  public function setUser(?User $user): static {
    $this->user = $user;
    return $this;
  }

  public function getTrigger(): ?UserSecurityCodeTrigger {
    return $this->trigger;
  }

  public function setTrigger(UserSecurityCodeTrigger $trigger): static {
    $this->trigger = $trigger;
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
