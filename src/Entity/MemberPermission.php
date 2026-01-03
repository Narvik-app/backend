<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use App\Entity\Abstract\UuidEntity;
use App\Entity\Club;
use App\Entity\ClubDependent\Member;
use App\Entity\Interface\ClubLinkedEntityInterface;
use App\Enum\ClubRole;
use App\Enum\Permission;
use App\Repository\MemberPermissionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: MemberPermissionRepository::class)]
#[ORM\Table(name: 'member_permission')]
#[ORM\UniqueConstraint(name: 'member_permission_unique', columns: ['member_id', 'permission'])]
#[UniqueEntity(fields: ['member', 'permission'], message: 'Permission already granted')]
#[ApiResource(
  uriTemplate: '/clubs/{clubUuid}/members/{memberUuid}/permissions/{uuid}',
  operations: [
    new GetCollection(
      uriTemplate: '/clubs/{clubUuid}/members/{memberUuid}/permissions.{_format}',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
        'memberUuid' => new Link(toProperty: 'member', fromClass: Member::class),
      ],
      security: "is_granted('".ClubRole::admin->value."', request)",
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
    )
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
class MemberPermission extends UuidEntity implements ClubLinkedEntityInterface {

  public static function getClubSqlPath(): string {
    return "member.club";
  }

  #[ORM\ManyToOne(targetEntity: Member::class, inversedBy: 'permissions')]
  #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
  #[Groups(['member-permission'])]
  private ?Member $member = null;

  #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, enumType: Permission::class)]
  #[Groups(['member-permission', 'member-permission-read'])]
  private Permission $permission;

  public function getMember(): ?Member {
    return $this->member;
  }

  public function setMember(?Member $member): static {
    $this->member = $member;
    return $this;
  }

  public function getPermission(): Permission {
    return $this->permission;
  }

  public function setPermission(Permission $permission): static {
    $this->permission = $permission;
    return $this;
  }

  // ClubLinkedEntityInterface
  public function getClub(): ?Club {
    return $this->member?->getClub();
  }

  public function setClub(?Club $club): static {
    // Club is set via member, not directly
    return $this;
  }
}
