<?php

namespace App\Entity;

use App\Entity\ClubDependent\Member;
use App\Enum\ClubRole;
use App\Repository\UserMemberRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: UserMemberRepository::class)]
#[UniqueEntity(fields: ['member'], message: 'Member already linked')]
class UserMember {
  #[ORM\Id]
  #[ORM\GeneratedValue(strategy: 'SEQUENCE')]
  #[ORM\Column]
  private ?int $id = null;

  #[ORM\ManyToOne(inversedBy: 'memberships')]
  #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
  private ?User $user = null;

  #[ORM\OneToOne(inversedBy: 'userMember', targetEntity: Member::class)]
  #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
  private ?Member $member = null;

  #[ORM\ManyToOne(targetEntity: Club::class)]
  #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
  private ?Club $badgerClub = null;

  #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, enumType: ClubRole::class)]
  private ClubRole $role = ClubRole::member;

  public function getId(): ?int {
    return $this->id;
  }

  public function getUser(): ?User {
    return $this->user;
  }

  public function setUser(?User $user): static {
    $this->user = $user;
    return $this;
  }

  public function getMember(): ?Member {
    return $this->member;
  }

  public function setMember(Member $member): static {
    $this->member = $member;
    return $this;
  }

  public function getBadgerClub(): ?Club {
    return $this->badgerClub;
  }

  public function setBadgerClub(?Club $club): static {
    $this->badgerClub = $club;
    return $this;
  }

  public function getRole(): ClubRole {
    return $this->role;
  }

  public function setRole(ClubRole $role): UserMember {
    $this->role = $role;
    return $this;
  }
}


