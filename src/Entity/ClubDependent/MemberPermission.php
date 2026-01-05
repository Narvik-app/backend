<?php

namespace App\Entity\ClubDependent;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use App\Entity\Abstract\UuidEntity;
use App\Entity\Club;
use App\Entity\Interface\ClubLinkedEntityInterface;
use App\Enum\ClubRole;
use App\Enum\Permission;
use App\Repository\MemberPermissionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: MemberPermissionRepository::class)]
#[ORM\UniqueConstraint(name: 'member_permission_unique', fields: ['permission', 'member'])]
#[ORM\UniqueConstraint(name: 'template_permission_unique', fields: ['permission', 'template'])]
#[UniqueEntity(fields: ['permission', 'member'], message: 'Permission already granted', ignoreNull: true)]
#[UniqueEntity(fields: ['permission', 'template'], message: 'Permission already exists in this template', ignoreNull: true)]
#[ApiResource(
  uriTemplate: '/clubs/{clubUuid}/members/{memberUuid}/permissions/{uuid}',
  operations: [
    // Member permissions endpoints
    new GetCollection(
      uriTemplate: '/clubs/{clubUuid}/members/{memberUuid}/permissions.{_format}',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
        'memberUuid' => new Link(toProperty: 'member', fromClass: Member::class),
      ],
      security: "is_granted('".ClubRole::supervisor->value."', request)",
    ),

    new Post(
      uriTemplate: '/clubs/{clubUuid}/members/{memberUuid}/permissions',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
        'memberUuid' => new Link(toProperty: 'member', fromClass: Member::class),
      ],
      securityPostDenormalize: "is_granted('".ClubRole::admin->value."', request)",
      read: false
    ),

    new Get(
      security: "is_granted('".ClubRole::admin->value."', object)",
    ),
    new Delete(
      security: "is_granted('".ClubRole::admin->value."', object)",
    ),

    // Template permissions endpoints
    new GetCollection(
      uriTemplate: '/clubs/{clubUuid}/permission-templates/{templateUuid}/permissions.{_format}',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
        'templateUuid' => new Link(toProperty: 'template', fromClass: PermissionTemplate::class),
      ],
      security: "is_granted('".ClubRole::supervisor->value."', request)",
    ),

    new Post(
      uriTemplate: '/clubs/{clubUuid}/permission-templates/{templateUuid}/permissions',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
        'templateUuid' => new Link(toProperty: 'template', fromClass: PermissionTemplate::class),
      ],
      securityPostDenormalize: "is_granted('".ClubRole::admin->value."', request)",
      read: false
    ),

    new Delete(
      uriTemplate: '/clubs/{clubUuid}/permission-templates/{templateUuid}/permissions/{uuid}',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
        'templateUuid' => new Link(toProperty: 'template', fromClass: PermissionTemplate::class),
        'uuid' => new Link(fromClass: self::class),
      ],
      security: "is_granted('".ClubRole::admin->value."', object)",
    ),
  ],
  uriVariables: [
    'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
    'memberUuid' => new Link(toProperty: 'member', fromClass: Member::class),
    'uuid' => new Link(fromClass: self::class),
  ],
  normalizationContext: [
    'groups' => ['member-permission', 'member-permission-read', 'common-read']
  ],
  denormalizationContext: [
    'groups' => ['member-permission', 'member-permission-write']
  ],
)]
#[ApiFilter(SearchFilter::class, properties: ['member.uuid' => 'exact', 'template.uuid' => 'exact'])]
class MemberPermission extends UuidEntity implements ClubLinkedEntityInterface {
  public static function getClubSqlPath(): string {
    // Club can be resolved via member or template
    return "COALESCE(member.club, template.club)";
  }

  #[ORM\ManyToOne(targetEntity: Member::class, inversedBy: 'permissions')]
  #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
  #[Groups(['member-permission'])]
  private ?Member $member = null;

  #[ORM\ManyToOne(targetEntity: PermissionTemplate::class, inversedBy: 'permissions')]
  #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
  #[Groups(['member-permission'])]
  private ?PermissionTemplate $template = null;

  #[ORM\Column(type: Types::STRING, enumType: Permission::class)]
  #[Groups(['member-permission', 'member-permission-read', 'permission-template-read'])]
  private Permission $permission;

  public function getClub(): ?Club {
    return $this->member?->getClub() ?? $this->template?->getClub();
  }

  public function setClub(?Club $club): static {
    // Club is set via member or template, not directly
    return $this;
  }

  public function getMember(): ?Member {
    return $this->member;
  }

  public function setMember(?Member $member): static {
    $this->member = $member;
    return $this;
  }

  public function getTemplate(): ?PermissionTemplate {
    return $this->template;
  }

  public function setTemplate(?PermissionTemplate $template): static {
    $this->template = $template;
    return $this;
  }

  public function getPermission(): Permission {
    return $this->permission;
  }

  public function setPermission(Permission $permission): static {
    $this->permission = $permission;
    return $this;
  }

  /**
   * Validates that either member OR template is set, but not both and not neither
   */
  #[Assert\Callback]
  public function validateMemberOrTemplate(ExecutionContextInterface $context): void {
    if ($this->member === null && $this->template === null) {
      $context->buildViolation('Either member or template must be set.')
        ->atPath('member')
        ->addViolation();
    }

    if ($this->member !== null && $this->template !== null) {
      $context->buildViolation('Permission cannot be assigned to both member and template.')
        ->atPath('member')
        ->addViolation();
    }
  }
}
