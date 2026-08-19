<?php

namespace App\Entity\ClubDependent;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\OpenApi\Model;
use App\Controller\ClubDependent\MemberControlTypeMove;
use App\Entity\Abstract\UuidEntity;
use App\Entity\Club;
use App\Entity\Interface\ClubLinkedEntityInterface;
use App\Entity\Interface\SortableEntityInterface;
use App\Entity\Trait\SelfClubLinkedEntityTrait;
use App\Enum\Permission;
use App\Repository\ClubDependent\MemberControlTypeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MemberControlTypeRepository::class)]
#[UniqueEntity(fields: ['name', 'club'])]
#[UniqueEntity(fields: ['weight', 'club'], ignoreNull: true)]
#[Assert\Expression(
  expression: "this.getWarningDays() === null or this.getAlertDays() === null or this.getWarningDays() <= this.getAlertDays()",
  message: "The warning delay must be shorter than (or equal to) the alert delay.",
)]
#[ApiResource(
  uriTemplate: '/clubs/{clubUuid}/member-control-types/{uuid}',
  operations: [
    new GetCollection(
      uriTemplate: '/clubs/{clubUuid}/member-control-types.{_format}',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
      ],
      security: "is_granted('".Permission::MEMBER_CONTROL_TYPES_ACCESS->value."', request)",
    ),
    new Post(
      uriTemplate: '/clubs/{clubUuid}/member-control-types.{_format}',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
      ],
      security: "is_granted('".Permission::MEMBER_CONTROL_TYPES_EDIT->value."', request)",
      read: false
    ),

    new Get(
      security: "is_granted('".Permission::MEMBER_CONTROL_TYPES_ACCESS->value."', object)"
    ),
    new Patch(
      security: "is_granted('".Permission::MEMBER_CONTROL_TYPES_EDIT->value."', object)",
    ),
    new Delete(
      security: "is_granted('".Permission::MEMBER_CONTROL_TYPES_EDIT->value."', object)",
    ),

    new Put(
      uriTemplate: '/clubs/{clubUuid}/member-control-types/{uuid}/move',
      controller: MemberControlTypeMove::class,
      openapi: new Model\Operation(
        description: 'Move `up` or `down` a member control type',
        requestBody: new Model\RequestBody(
          content: new \ArrayObject([
            'application/json' => [
              'schema' => [
                'type' => 'object',
                'properties' => [
                  'direction' => ['type' => 'string'],
                ]
              ]
            ]
          ])
        )
      ),
      security: "is_granted('".Permission::MEMBER_CONTROL_TYPES_EDIT->value."', object)",
    )
  ],
  uriVariables: [
    'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
    'uuid' => new Link(fromClass: self::class),
  ],
  normalizationContext: [
    'groups' => ['member-control-type', 'member-control-type-read']
  ],
  denormalizationContext: [
    'groups' => ['member-control-type', 'member-control-type-write']
  ],
  order: ['weight' => 'asc'],
)]
#[ApiFilter(OrderFilter::class, properties: ['weight' => 'ASC', 'name' => 'ASC'])]
class MemberControlType extends UuidEntity implements ClubLinkedEntityInterface, SortableEntityInterface {
  use SelfClubLinkedEntityTrait;

  // NOTE: 'member-read'/'member-presence-read' are included here (not just 'member-control-read')
  // so this data still appears when a MemberControlType is nested inside a Member payload via
  // Member::$controls — API Platform requires the nested object's own property groups to match
  // the parent's normalization context, it doesn't inherit it. See Activity for the same pattern.
  #[ORM\Column(length: 255)]
  #[Groups(['member-control-type', 'member-control-read', 'member-read', 'member-presence-read'])]
  #[Assert\NotBlank]
  private ?string $name = null;

  #[ORM\Column(length: 255, nullable: true)]
  #[Groups(['member-control-type', 'member-control-read', 'member-read', 'member-presence-read'])]
  private ?string $icon = null;

  #[ORM\ManyToOne(targetEntity: Activity::class)]
  #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
  #[Groups(['member-control-type', 'member-control-read', 'member-read', 'member-presence-read'])]
  private ?Activity $activity = null;

  #[ORM\Column(nullable: true)]
  #[Groups(['member-control-type', 'member-control-read', 'member-read', 'member-presence-read'])]
  #[Assert\Positive]
  private ?int $warningDays = null;

  #[ORM\Column(nullable: true)]
  #[Groups(['member-control-type', 'member-control-read', 'member-read', 'member-presence-read'])]
  #[Assert\Positive]
  private ?int $alertDays = null;

  #[ORM\Column(options: ['default' => true])]
  #[Groups(['member-control-type', 'member-control-read', 'member-read', 'member-presence-read'])]
  private bool $displayOnPresenceCard = true;

  #[ORM\Column(nullable: true)]
  #[Groups(['member-control-type', 'member-read', 'member-presence-read'])]
  private ?int $weight = null;

  /**
   * @var Collection<int, MemberControl>
   */
  #[ORM\OneToMany(mappedBy: 'type', targetEntity: MemberControl::class, orphanRemoval: true)]
  private Collection $controls;

  public function __construct() {
    parent::__construct();
    $this->controls = new ArrayCollection();
  }

  public function getName(): ?string {
    return $this->name;
  }

  public function setName(string $name): static {
    $this->name = ucfirst($name);
    return $this;
  }

  public function getIcon(): ?string {
    return $this->icon;
  }

  public function setIcon(?string $icon): static {
    $this->icon = $icon;
    return $this;
  }

  public function getActivity(): ?Activity {
    return $this->activity;
  }

  public function setActivity(?Activity $activity): static {
    $this->activity = $activity;
    return $this;
  }

  public function isAutomatic(): bool {
    return $this->activity !== null;
  }

  public function getWarningDays(): ?int {
    return $this->warningDays;
  }

  public function setWarningDays(?int $warningDays): static {
    $this->warningDays = $warningDays;
    return $this;
  }

  public function getAlertDays(): ?int {
    return $this->alertDays;
  }

  public function setAlertDays(?int $alertDays): static {
    $this->alertDays = $alertDays;
    return $this;
  }

  public function isDisplayOnPresenceCard(): bool {
    return $this->displayOnPresenceCard;
  }

  public function setDisplayOnPresenceCard(bool $displayOnPresenceCard): static {
    $this->displayOnPresenceCard = $displayOnPresenceCard;
    return $this;
  }

  public function getWeight(): ?int {
    return $this->weight;
  }

  public function setWeight(int $weight): static {
    $this->weight = $weight;
    return $this;
  }

  /**
   * @return Collection<int, MemberControl>
   */
  public function getControls(): Collection {
    return $this->controls;
  }
}
