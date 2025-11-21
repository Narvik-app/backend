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
use ApiPlatform\OpenApi\Model;
use App\Controller\ClubDependent\Plugin\Presence\ActivityMerge;
use App\Entity\Abstract\UuidEntity;
use App\Entity\Club;
use App\Entity\ClubDependent\Plugin\Presence\ExternalPresence;
use App\Entity\ClubDependent\Plugin\Presence\MemberPresence;
use App\Entity\Interface\ClubLinkedEntityInterface;
use App\Entity\Trait\SelfClubLinkedEntityTrait;
use App\Enum\ClubRole;
use App\Repository\ClubDependent\ActivityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;


#[ORM\Entity(repositoryClass: ActivityRepository::class)]
#[UniqueEntity(fields: ['name', 'club'])]
#[ApiResource(
  uriTemplate: '/clubs/{clubUuid}/activities/{uuid}.{_format}',
  operations: [
    new GetCollection(
      uriTemplate: '/clubs/{clubUuid}/activities.{_format}',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
      ],
      security: "is_granted('".ClubRole::member->value."', request) || is_granted('".ClubRole::badger->value."', request)",
    ),
    new Post(
      uriTemplate: '/clubs/{clubUuid}/activities.{_format}',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
      ],
      securityPostDenormalize: "is_granted('".ClubRole::admin->value."', request)",
      read: false,
    ),

    new Get(),
    new Patch(
      security: "is_granted('".ClubRole::admin->value."', object)",
    ),
    new Delete(
      security: "is_granted('".ClubRole::admin->value."', object)"
    ),

    new Patch(
      uriTemplate: '/clubs/{clubUuid}/activities/{uuid}/merge',
      controller: ActivityMerge::class,
      openapi: new Model\Operation(
        summary: 'Merge (and then delete) with another activity',
        requestBody: new Model\RequestBody(
          content: new \ArrayObject([
            'application/json' => [
              'schema' => [
                'type'       => 'object',
                'properties' => [
                  'target' => ['type' => 'string', 'description' => 'Activity uuid target', 'required' => true],
                ],
              ],
            ],
          ]),
        ),
      ),
      security: "is_granted('".ClubRole::admin->value."', object)",
    )
  ],
  uriVariables: [
    'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
    'uuid' => new Link(fromClass: self::class),
  ],
  normalizationContext: [
    'groups' => ['activity', 'activity-read']
  ],
  denormalizationContext: [
    'groups' => ['activity', 'activity-write']
  ],
  order: ['name' => 'asc'],
)]
#[ApiFilter(OrderFilter::class, properties: ['name' => 'ASC', 'isEnabled' => 'ASC'])]
class Activity extends UuidEntity implements ClubLinkedEntityInterface {
  use SelfClubLinkedEntityTrait;

  #[ORM\Column(length: 255)]
  #[Groups(['club-admin-write', 'activity-read','member-read', 'member-presence', 'external-presence'])]
  #[Assert\NotBlank()]
  private string $name;

  #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, nullable: true, enumType: ClubRole::class)]
  #[Groups(['club-admin-write', 'activity-read','member-read', 'member-presence', 'external-presence'])]
  private ClubRole|null $visibility = null;

  #[ORM\Column(type: \Doctrine\DBAL\Types\Types::BOOLEAN)]
  #[Groups(['club-admin-write', 'activity-read','member-read', 'member-presence', 'external-presence'])]
  private ?bool $isEnabled = true;

  #[ORM\ManyToMany(targetEntity: MemberPresence::class, mappedBy: 'activities')]
  private Collection $memberPresences;

  #[ORM\ManyToMany(targetEntity: ExternalPresence::class, mappedBy: 'activities')]
  private Collection $externalPresences;

  public function __construct() {
    parent::__construct();
    $this->memberPresences = new ArrayCollection();
    $this->externalPresences = new ArrayCollection();
  }

  public function getName(): string {
    return $this->name;
  }

  public function setName(string $name): static {
    $this->name = trim($name);
    return $this;
  }

  public function getVisibility(): ?ClubRole {
    return $this->visibility;
  }

  public function setVisibility(?ClubRole $visibility): Activity {
    // Member visibility = visible for everybody
    if ($visibility === ClubRole::member) {
      $visibility = null;
    }
    $this->visibility = $visibility;
    return $this;
  }

  public function getIsEnabled(): ?bool {
    return $this->isEnabled;
  }

  public function setIsEnabled(?bool $isEnabled): Activity {
    $this->isEnabled = $isEnabled;
    return $this;
  }

  /**
   * @return Collection<int, MemberPresence>
   */
  public function getMemberPresences(): Collection {
    return $this->memberPresences;
  }

  public function addMemberPresence(MemberPresence $memberPresence): static {
    if (!$this->memberPresences->contains($memberPresence)) {
      $this->memberPresences->add($memberPresence);
      $memberPresence->addActivity($this);
    }
    return $this;
  }

  public function removeMemberPresence(MemberPresence $memberPresence): static {
    if ($this->memberPresences->removeElement($memberPresence)) {
      $memberPresence->removeActivity($this);
    }
    return $this;
  }

  /**
   * @return Collection<int, ExternalPresence>
   */
  public function getExternalPresences(): Collection {
    return $this->externalPresences;
  }

  public function addExternalPresence(ExternalPresence $externalPresence): static {
    if (!$this->externalPresences->contains($externalPresence)) {
      $this->externalPresences->add($externalPresence);
      $externalPresence->addActivity($this);
    }
    return $this;
  }

  public function removeExternalPresence(ExternalPresence $externalPresence): static {
    if ($this->externalPresences->removeElement($externalPresence)) {
      $externalPresence->removeActivity($this);
    }
    return $this;
  }
}
