<?php

namespace App\Entity\ClubDependent\Plugin\Loan;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Entity\Abstract\UuidEntity;
use App\Entity\Club;
use App\Entity\Interface\ClubLinkedEntityInterface;
use App\Entity\Trait\SelfClubLinkedEntityTrait;
use App\Enum\Permission;
use App\Repository\ClubDependent\Plugin\Loan\LoanRecordingTypeRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: LoanRecordingTypeRepository::class)]
#[UniqueEntity(fields: ['name', 'club'], ignoreNull: true)]
#[ApiResource(
  uriTemplate: '/clubs/{clubUuid}/loan-recording-types/{uuid}',
  operations: [
    new GetCollection(
      uriTemplate: '/clubs/{clubUuid}/loan-recording-types.{_format}',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
      ],
      security: "is_granted('".Permission::LOAN_RECORDINGS_ACCESS->value."', request)",
    ),
    new Post(
      uriTemplate: '/clubs/{clubUuid}/loan-recording-types.{_format}',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
      ],
      security: "is_granted('".Permission::LOAN_RECORDINGS_EDIT->value."', request)",
      read: false
    ),

    new Get(
      security: "is_granted('".Permission::LOAN_RECORDINGS_ACCESS->value."', object)",
    ),
    new Patch(
      security: "is_granted('".Permission::LOAN_RECORDINGS_EDIT->value."', object)",
    ),
    new Delete(
      security: "is_granted('".Permission::LOAN_RECORDINGS_EDIT->value."', object)",
    ),
  ],
  uriVariables: [
    'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
    'uuid' => new Link(fromClass: self::class),
  ],
  normalizationContext: [
    'groups' => ['loan-recording-type', 'loan-recording-type-read']
  ],
  denormalizationContext: [
    'groups' => ['loan-recording-type', 'loan-recording-type-write']
  ],
  order: ['name' => 'asc'],
)]
#[ApiFilter(OrderFilter::class, properties: ['name' => 'ASC'])]
class LoanRecordingType extends UuidEntity implements ClubLinkedEntityInterface {
  use SelfClubLinkedEntityTrait;

  #[ORM\Column(length: 255)]
  #[Groups(['loan-recording-type', 'loan-recording-read'])]
  #[Assert\NotBlank]
  private ?string $name = null;

  /** Hex color, e.g. #3b82f6 */
  #[ORM\Column(length: 20, nullable: true)]
  #[Groups(['loan-recording-type', 'loan-recording-read'])]
  private ?string $color = null;

  public function getName(): ?string {
    return $this->name;
  }

  public function setName(string $name): static {
    $this->name = ucfirst($name);
    return $this;
  }

  public function getColor(): ?string {
    return $this->color;
  }

  public function setColor(?string $color): static {
    $this->color = $color;
    return $this;
  }
}
