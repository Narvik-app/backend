<?php

namespace App\Entity\ClubDependent;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
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
use App\Enum\ClubRole;
use App\Repository\ClubDependent\PermissionTemplateRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PermissionTemplateRepository::class)]
#[ORM\UniqueConstraint(name: 'permission_template_unique', fields: ['name', 'club'])]
#[UniqueEntity(fields: ['name', 'club'], message: 'A template with this name already exists')]
#[ApiResource(
  uriTemplate: '/clubs/{clubUuid}/permission-templates/{uuid}',
  operations: [
    new GetCollection(
      uriTemplate: '/clubs/{clubUuid}/permission-templates.{_format}',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
      ],
      security: "is_granted('".ClubRole::supervisor->value."', request)",
    ),

    new Post(
      uriTemplate: '/clubs/{clubUuid}/permission-templates',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
      ],
      securityPostDenormalize: "is_granted('".ClubRole::admin->value."', request)",
      read: false
    ),

    new Get(
      security: "is_granted('".ClubRole::supervisor->value."', object)",
    ),
    new Patch(
      security: "is_granted('".ClubRole::admin->value."', object)",
    ),
    new Delete(
      security: "is_granted('".ClubRole::admin->value."', object)",
    )
  ],
  uriVariables: [
    'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
    'uuid' => new Link(fromClass: self::class),
  ],
  normalizationContext: [
    'groups' => ['permission-template', 'permission-template-read', 'common-read']
  ],
  denormalizationContext: [
    'groups' => ['permission-template', 'permission-template-write']
  ],
)]
#[ApiFilter(SearchFilter::class, properties: ['name' => 'partial'])]
class PermissionTemplate extends UuidEntity implements ClubLinkedEntityInterface {
  use SelfClubLinkedEntityTrait;

  #[ORM\Column(length: 255)]
  #[Groups(['permission-template', 'member-read'])]
  #[Assert\NotBlank]
  private ?string $name = null;

  /**
   * @var Collection<int, MemberPermission>
   */
  #[ORM\OneToMany(mappedBy: 'template', targetEntity: MemberPermission::class, orphanRemoval: true)]
  private Collection $permissions;

  public function __construct() {
    parent::__construct();
    $this->permissions = new ArrayCollection();
  }

  public function getName(): ?string {
    return $this->name;
  }

  public function setName(string $name): static {
    $this->name = $name;
    return $this;
  }

  #[Groups(['permission-template-read'])]
  #[SerializedName('permissionsCount')]
  public function getPermissionsCount(): int {
    return $this->permissions->count();
  }

  /**
   * @return Collection<int, MemberPermission>
   */
  public function getPermissions(): Collection {
    return $this->permissions;
  }

  public function addPermission(MemberPermission $permission): static {
    if (!$this->permissions->contains($permission)) {
      $this->permissions->add($permission);
      $permission->setTemplate($this);
    }
    return $this;
  }

  public function removePermission(MemberPermission $permission): static {
    if ($this->permissions->removeElement($permission)) {
      if ($permission->getTemplate() === $this) {
        $permission->setTemplate(null);
      }
    }
    return $this;
  }

  /**
   * Check if template has a specific permission
   */
  public function hasPermission(\App\Enum\Permission $permission): bool {
    foreach ($this->permissions as $memberPermission) {
      if ($memberPermission->getPermission() === $permission) {
        return true;
      }
    }
    return false;
  }
}
