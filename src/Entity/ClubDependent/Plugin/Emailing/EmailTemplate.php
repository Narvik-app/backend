<?php

namespace App\Entity\ClubDependent\Plugin\Emailing;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use App\Entity\Abstract\UuidEntity;
use App\Entity\Club;
use App\Entity\Interface\ClubLinkedEntityInterface;
use App\Entity\Interface\TimestampEntityInterface;
use App\Entity\Trait\SelfClubLinkedEntityTrait;
use App\Entity\Trait\TimestampTrait;
use App\Enum\ClubRole;
use App\Repository\ClubDependent\Plugin\Emailing\EmailTemplateRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EmailTemplateRepository::class)]
#[ApiResource(uriTemplate: '/clubs/{clubUuid}/email-templates/{uuid}', operations: [
    new GetCollection(
      uriTemplate: '/clubs/{clubUuid}/email-templates.{_format}',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
      ],
      security: "is_granted('" . ClubRole::admin->value . "', request)",
    ),
    new Get(security: "is_granted('" . ClubRole::admin->value . "', object)",),
    new Patch(security: "is_granted('" . ClubRole::admin->value . "', object)",),
    new Delete(security: "is_granted('" . ClubRole::admin->value . "', request)",),

  ], uriVariables: [
    'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
    'uuid'     => new Link(fromClass: self::class),
  ], normalizationContext: [
    'groups' => ['email-template', 'email-template-read']
  ], denormalizationContext: [
    'groups' => ['email-template', 'email-template-write']
  ], order: ['createdAt' => 'DESC'],)]
#[ApiFilter(OrderFilter::class, properties: ['createdAt' => 'DESC'])]
class EmailTemplate extends UuidEntity implements TimestampEntityInterface, ClubLinkedEntityInterface {
  use TimestampTrait;
  use SelfClubLinkedEntityTrait;

  #[ORM\Column]
  #[Groups(['email-template'])]
  private bool $isNewsletter = true;

  #[ORM\Column(length: 255)]
  #[Groups(['email-template'])]
  #[Assert\NotBlank(allowNull: false)]
  private ?string $title = null;

  #[ORM\Column(type: Types::TEXT)]
  #[Groups(['email-template'])]
  #[Assert\NotBlank(allowNull: false)]
  private ?string $content = null;

  public function getId(): ?int {
    return $this->id;
  }

  public function getTitle(): ?string {
    return $this->title;
  }

  public function setTitle(string $title): static {
    $this->title = $title;

    return $this;
  }

  public function getContent(): ?string {
    return $this->content;
  }

  public function setContent(string $content): static {
    $this->content = $content;
    return $this;
  }

  public function getIsNewsletter(): bool {
      return $this->isNewsletter;
  }

  public function setIsNewsletter(bool $isNewsletter): static {
      $this->isNewsletter = $isNewsletter;
      return $this;
  }
}
