<?php

namespace App\Entity;

use App\Entity\ClubDependent\Member;
use App\Enum\ClubRole;
use App\Enum\Permission;
use Symfony\Component\Serializer\Attribute\Groups;

class Profile {
  #[Groups(['common-read'])]
  private string $id;

  #[Groups(['common-read'])]
  private string $displayName;

  #[Groups(['common-read'])]
  private ?Club $club = null;

  #[Groups(['common-read'])]
  private ?Member $member = null;

  #[Groups(['common-read'])]
  private ClubRole $role;

  /**
   * @var Permission[]
   */
  #[Groups(['common-read'])]
  private array $permissions = [];

  public function getId(): string {
    return $this->id;
  }

  public function setId(string $id): Profile {
    $this->id = $id;
    return $this;
  }

  public function getDisplayName(): string {
    return $this->displayName;
  }

  public function setDisplayName(string $displayName): Profile {
    $this->displayName = $displayName;
    return $this;
  }

  public function getClub(): ?Club {
    return $this->club;
  }

  public function setClub(?Club $club): Profile {
    $this->club = $club;
    return $this;
  }

  public function getMember(): ?Member {
    return $this->member;
  }

  public function setMember(?Member $member): Profile {
    $this->member = $member;
    return $this;
  }

  public function getRole(): ClubRole {
    return $this->role;
  }

  public function setRole(ClubRole $role): Profile {
    $this->role = $role;
    return $this;
  }

  /**
   * @return Permission[]
   */
  public function getPermissions(): array {
    return $this->permissions;
  }

  /**
   * @param Permission[] $permissions
   */
  public function setPermissions(array $permissions): Profile {
    $this->permissions = $permissions;
    return $this;
  }
}

