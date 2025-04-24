<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Controller\ClubGenerateBadger;
use App\Entity\Abstract\UuidEntity;
use App\Entity\ClubDependent\ClubSetting;
use App\Entity\Interface\TimestampEntityInterface;
use App\Entity\Trait\TimestampTrait;
use App\Enum\ClubRole;
use App\Enum\UserRole;
use App\Filter\MultipleFilter;
use App\Repository\ClubRepository;
use Ddeboer\VatinBundle\Validator\Constraints\Vatin;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ClubRepository::class)]
#[UniqueEntity(fields: ['name'])]
#[ApiResource(operations: [
  new GetCollection(security: "is_granted('".UserRole::super_admin->value."')"), // Collection only to super admin, other should get them through /self
  new Get(),
  new Post(security: "is_granted('".UserRole::super_admin->value."')"),
  new Patch(security: "is_granted('".UserRole::super_admin->value."')",),
  new Delete(security: "is_granted('".UserRole::super_admin->value."')",),

  new Patch(
    uriTemplate: '/clubs/{uuid}/generate-badger',
    controller: ClubGenerateBadger::class,
    security: "is_granted('".ClubRole::admin->value."', object)",
    deserialize: false,
    write: false
  ),
], normalizationContext: [
  'groups' => ['club', 'club-read', 'common-read'],
], denormalizationContext: [
  'groups' => ['club', 'club-write'],
], order: ['renewDate' => 'ASC', 'isActivated' => 'ASC', 'name' => 'ASC'],)]
#[ApiFilter(OrderFilter::class, properties: ['isActivated' => 'ASC', 'renewDate' => 'ASC', 'name' => 'ASC', 'createdAt' => 'DESC'])]
#[ApiFilter(MultipleFilter::class, properties: ['name'])]
class Club extends UuidEntity implements TimestampEntityInterface {
  use TimestampTrait;

  #[ORM\Column(length: 255)]
  #[Groups(['common-read', 'super-admin-write'])]
  #[Assert\NotBlank]
  private ?string $name = null;

  #[ORM\Column(options: ['default' => true])]
  #[Groups(['club-read', 'super-admin-write'])]
  #[ApiProperty(securityPostDenormalize: "is_granted('".ClubRole::supervisor->value."', object)")] // Property can be read by club admin/supervisor
  private bool $isActivated = true;

  #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
  #[Groups(['club-read', 'super-admin-write'])]
  private ?\DateTimeImmutable $renewDate = null;

  #[ORM\Column(options: ['default' => false])]
  #[Groups(['club-read', 'super-admin-write'])]
  #[ApiProperty(securityPostDenormalize: "is_granted('".ClubRole::supervisor->value."', object)")] // Property can be read by club admin/supervisor
  private bool $salesEnabled = false;

  #[ORM\Column(length: 255, nullable: true)]
  #[Groups(['club-read', 'club-admin-write'])]
  #[ApiProperty(security: "is_granted('".ClubRole::admin->value."', object)")] // Property only viewable & writable by the club admin
  private ?string $badgerToken = null;

  #[ORM\Column(length: 255, nullable: true)]
  #[Groups(['super-admin-read', 'super-admin-write'])]
  private ?string $comment = null;

  #[ORM\Column(length: 255, nullable: true)]
  #[Groups(['super-admin-read', 'super-admin-write'])]
  private ?string $address = null;

  #[ORM\Column(nullable: true)]
  #[Groups(['super-admin-read', 'super-admin-write'])]
  private ?int $zipCode = null;

  #[ORM\Column(length: 255, nullable: true)]
  #[Groups(['super-admin-read', 'super-admin-write'])]
  private ?string $city = null;

  #[ORM\Column(length: 255, nullable: true)]
  #[Groups(['super-admin-read', 'super-admin-write'])]
  private ?string $siret = null;

  #[ORM\Column(length: 255, nullable: true)]
  #[Groups(['super-admin-read', 'super-admin-write'])]
  #[Vatin]
  private ?string $vat = null;

  #[ORM\Column(length: 255, nullable: true)]
  #[Groups(['super-admin-read', 'super-admin-write'])]
  private ?string $website = null;

  #[ORM\Column(length: 255, nullable: true)]
  #[Groups(['super-admin-read', 'super-admin-write'])]
  private ?string $contactName = null;

  #[ORM\Column(length: 255, nullable: true)]
  #[Groups(['super-admin-read', 'super-admin-write'])]
  private ?string $contactPhone = null;

  #[ORM\Column(length: 255, nullable: true)]
  #[Groups(['super-admin-read', 'super-admin-write'])]
  private ?string $contactEmail = null;

  #[ORM\OneToOne(mappedBy: 'club', targetEntity: ClubSetting::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
  #[Groups(['club-read'])]
  private ?ClubSetting $settings = null;

  public function __construct() {
    parent::__construct();
    $this->setSettings(new ClubSetting());
  }

  public function getName(): ?string {
    return $this->name;
  }

  public function setName(string $name): static {
    $this->name = $name;
    return $this;
  }

  public function getIsActivated(): bool {
    return $this->isActivated;
  }

  public function setIsActivated(bool $isActivated): Club {
    $this->isActivated = $isActivated;
    return $this;
  }

  public function getSalesEnabled(): ?bool {
    return $this->salesEnabled;
  }

  public function setSalesEnabled(bool $salesEnabled): static {
    $this->salesEnabled = $salesEnabled;
    return $this;
  }

  public function getBadgerToken(): ?string {
      return $this->badgerToken;
  }

  public function setBadgerToken(?string $badgerToken): static {
      $this->badgerToken = $badgerToken;
      return $this;
  }

  public function getComment(): ?string {
    return $this->comment;
  }

  public function setComment(?string $comment): Club {
    if (empty($comment)) {
      $comment = null;
    }
    $this->comment = $comment;
    return $this;
  }

  public function getSettings(): ?ClubSetting {
    return $this->settings;
  }

  public function setSettings(ClubSetting $setting): static {
    // set the owning side of the relation if necessary
    if ($setting->getClub() !== $this) {
      $setting->setClub($this);
    }

    $this->settings = $setting;
    return $this;
  }

  public function getRenewDate(): ?\DateTimeImmutable {
    return $this->renewDate;
  }

  public function setRenewDate(?\DateTimeImmutable $renewDate): Club {
    if ($renewDate) {
      $renewDate = $renewDate->setTime(23, 59, 59);
    }
    $this->renewDate = $renewDate;
    return $this;
  }

  public function getWebsite(): ?string {
    return $this->website;
  }

  public function setWebsite(?string $website): Club {
    $this->website = $website;
    return $this;
  }

  public function getContactName(): ?string {
    return $this->contactName;
  }

  public function setContactName(?string $contactName): Club {
    $this->contactName = $contactName;
    return $this;
  }

  public function getContactPhone(): ?string {
    return $this->contactPhone;
  }

  public function setContactPhone(?string $contactPhone): Club {
    $this->contactPhone = $contactPhone;
    return $this;
  }

  public function getContactEmail(): ?string {
    return $this->contactEmail;
  }

  public function setContactEmail(?string $contactEmail): Club {
    $this->contactEmail = $contactEmail;
    return $this;
  }

  public function getAddress(): ?string {
    return $this->address;
  }

  public function setAddress(?string $address): Club {
    $this->address = $address;
    return $this;
  }

  public function getZipCode(): ?int {
    return $this->zipCode;
  }

  public function setZipCode(?int $zipCode): Club {
    $this->zipCode = $zipCode;
    return $this;
  }

  public function getCity(): ?string {
    return $this->city;
  }

  public function setCity(?string $city): Club {
    $this->city = $city;
    return $this;
  }

  public function getSiret(): ?string {
    return $this->siret;
  }

  public function setSiret(?string $siret): Club {
    $this->siret = $siret;
    return $this;
  }

  public function getVat(): ?string {
    return $this->vat;
  }

  public function setVat(?string $vat): Club {
    $this->vat = $vat;
    return $this;
  }
}
