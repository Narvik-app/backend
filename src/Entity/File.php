<?php

namespace App\Entity;

use App\Entity\Abstract\UuidEntity;
use App\Entity\Interface\TimestampEntityInterface;
use App\Entity\Trait\TimestampTrait;
use App\Enum\FileCategory;
use App\Repository\FileRepository;
use App\Service\UuidService;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: FileRepository::class)]
class File extends UuidEntity implements TimestampEntityInterface {
  use TimestampTrait;

  private ?string $encodedUuid = null;

  #[Groups(['common-read'])]
  private ?string $publicUrl = null;

  #[Groups(['common-read'])]
  private ?string $publicInlineUrl = null;

  #[Groups(['common-read'])]
  private ?string $privateUrl = null;

  #[ORM\Column(type: "string", enumType: FileCategory::class)]
  private ?FileCategory $category = null;

  #[ORM\Column(length: 255, nullable: true)]
  private ?string $filename = null;

  #[ORM\Column(length: 255)]
  private ?string $path = null;

  #[ORM\Column(length: 255)]
  private ?string $mimeType = null;

  #[ORM\Column(type: 'boolean', options: ["default" => 0])]
  private bool $isPublic = false;

  #[ORM\ManyToOne(targetEntity: Club::class)]
  #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
  private ?Club $club = null;

  public function getEncodedUuid(): ?string {
    if (!$this->getUuid()) {
      return null;
    }
    return UuidService::encodeToReadable($this->getUuid());
  }

  public function getPublicUrl(): ?string {
    return $this->publicUrl;
  }

  public function setPublicUrl(?string $publicUrl): File {
    $this->publicUrl = $publicUrl;
    return $this;
  }

  public function getPublicInlineUrl(): ?string {
    return $this->publicInlineUrl;
  }

  public function setPublicInlineUrl(?string $publicInlineUrl): File {
    $this->publicInlineUrl = $publicInlineUrl;
    return $this;
  }

  public function getPrivateUrl(): ?string {
    return $this->privateUrl;
  }

  public function setPrivateUrl(?string $privateUrl): File {
    $this->privateUrl = $privateUrl;
    return $this;
  }

  public function getCategory(): ?FileCategory {
    return $this->category;
  }

  public function setCategory(?FileCategory $category): File {
    $this->category = $category;
    return $this;
  }

  public function getFilename(): ?string {
    return $this->filename;
  }

  public function setFilename(?string $filename): static {
    $this->filename = $filename;
    return $this;
  }

  public function getPath(): ?string {
    return $this->path;
  }

  public function setPath(string $path): static {
    $this->path = $path;
    return $this;
  }

  public function getMimeType(): ?string {
    return $this->mimeType;
  }

  public function setMimeType(string $mimeType): static {
    $this->mimeType = $mimeType;
    return $this;
  }

  public function getIsPublic(): bool {
    return $this->isPublic;
  }

  public function setIsPublic(bool $isPublic): static {
    $this->isPublic = $isPublic;
    return $this;
  }

  public function getClub(): ?Club {
    return $this->club;
  }

  public function setClub(?Club $club): static {
    $this->club = $club;
    return $this;
  }
}
