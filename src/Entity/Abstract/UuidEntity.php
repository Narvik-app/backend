<?php

namespace App\Entity\Abstract;

use ApiPlatform\Metadata\ApiProperty;
use App\Entity\Interface\UuidEntityInterface;
use App\Service\UuidService;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Serializer\Attribute\Groups;

abstract class UuidEntity implements UuidEntityInterface {
  #[ORM\Id]
  #[ORM\GeneratedValue(strategy: 'SEQUENCE')]
  #[ORM\Column]
  #[ApiProperty(identifier: false)]
  protected ?int $id = null;

  #[ApiProperty(identifier: true)]
  #[ORM\Column(type: 'uuid', unique: true)]
  #[Groups(['common-read'])]
  protected ?Uuid $uuid = null;

  public function __construct() {
    $this->uuid = UuidService::generateUuid();
  }

  public function getId(): ?int {
    return $this->id;
  }

  public function getUuid(): ?Uuid {
    return $this->uuid;
  }

  public function setUuid(Uuid $uuid): static {
    $this->uuid = $uuid;
    return $this;
  }
}
