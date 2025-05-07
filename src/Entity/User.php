<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\OpenApi\Model;
use App\Controller\UserPasswordReset;
use App\Controller\UserPasswordResetInitiate;
use App\Controller\UserInitiateRegister;
use App\Controller\UserSelf;
use App\Controller\UserSelfDeleteAccount;
use App\Controller\UserSelfLegalsAccepted;
use App\Controller\UserSelfUpdatePassword;
use App\Controller\UserRegister;
use App\Entity\Abstract\UuidEntity;
use App\Entity\ClubDependent\Member;
use App\Entity\Interface\TimestampEntityInterface;
use App\Entity\Trait\TimestampTrait;
use App\Enum\UserRole;
use App\Filter\MultipleFilter;
use App\Repository\UserRepository;
use App\State\UserProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[UniqueEntity(fields: ['email'])]
#[ApiResource(operations: [
  new GetCollection(),
  new Post(),
  new Get(),
  new Patch(),
  new Delete(),

  new Get(
    uriTemplate: '/self',
    controller: UserSelf::class,
    openapi: new Model\Operation(summary: 'Return current logged user information',),
    normalizationContext: ['groups' => ['self-read', 'user-read', 'user']],
    read: false,
  ),
  new Delete(
    uriTemplate: '/self',
    controller: UserSelfDeleteAccount::class,
    openapi: new Model\Operation(summary: 'Delete the logged user account. This action is permanent. It won\'t delete the data registered by each club the user was member. User must do a request to his club for them to remove the data linked to his licence/email.',),
    read: false,
  ),
  new Put(
    uriTemplate: '/self/update-password',
    controller: UserSelfUpdatePassword::class,
    openapi:
      new Model\Operation(
        summary: 'Change current user password',
        requestBody:
          new Model\RequestBody(
            content: new \ArrayObject([
              'application/json' => [
                'schema' => [
                  'type'       => 'object',
                  'properties' => [
                    'current' => ['type' => 'string'],
                    'new'     => ['type' => 'string'],
                  ],
                ],
              ],
            ]),
          ),
      ),
    read: false,
    write: false
  ),
  new Patch(
    uriTemplate: '/self/legals-accepted',
    controller: UserSelfLegalsAccepted::class,
    openapi:
    new Model\Operation(
      summary: 'User accepted the latest legals informations.',
    ),
    read: false,
    write: false
  ),

  new Post(
    uriTemplate: '/users/-/initiate-register',
    controller: UserInitiateRegister::class,
    openapi:
    new Model\Operation(
      summary: 'Trigger the user account creation logic. Will send an email with a securityCode. Email must be enabled for this to work',
      requestBody:
      new Model\RequestBody(
        content: new \ArrayObject([
          'application/json' => [
            'schema' => [
              'type'       => 'object',
              'properties' => [
                'email'     => ['type' => 'string'],
                'token'     => ['type' => 'string'],
              ],
            ],
          ],
        ]),
      )
      ,
    ),
    read: false,
    deserialize: false,
    write: false,
    serialize: false,
  ),
  new Post(
    uriTemplate: '/users/-/register',
    controller: UserRegister::class,
    openapi:
    new Model\Operation(
      summary: 'Validate the user account creation. If securityCode is invalid a new one will be sent.',
      requestBody:
      new Model\RequestBody(
        content: new \ArrayObject([
          'application/json' => [
            'schema' => [
              'type'       => 'object',
              'properties' => [
                'securityCode' => ['type' => 'string'],
                'accountType'  => ['type' => 'string'],

                'email'     => ['type' => 'string'],
                'firstname' => ['type' => 'string'],
                'lastname'  => ['type' => 'string'],
                'password'  => ['type' => 'string'],

                'clubActivity' => ['type' => 'string'],
                'clubName'     => ['type' => 'string'],
                'clubSiret'    => ['type' => 'string'],
                'clubVat'      => ['type' => 'string'],
                'clubEmail'    => ['type' => 'string'],
                'clubPhone'    => ['type' => 'string'],
                'clubAddress'  => ['type' => 'string'],
                'clubZipCode'  => ['type' => 'string'],
                'clubCity'     => ['type' => 'string'],
              ],
            ],
          ],
        ]),
      ),
    ),
    read: false,
    deserialize: false,
    write: false,
    serialize: false
  ),

  new Post(
    uriTemplate: '/users/-/initiate-reset-password',
    controller: UserPasswordResetInitiate::class,
    openapi:
      new Model\Operation(
        summary: 'Trigger the reset password logic. Will send an email with a securityCode. Email must be enabled for this to work',
        requestBody:
          new Model\RequestBody(
            content: new \ArrayObject([
              'application/json' => [
                'schema' => [
                  'type'       => 'object',
                  'properties' => [
                    'email' => ['type' => 'string'],
                    'token'     => ['type' => 'string'],
                  ],
                ],
              ],
            ]),
          )
        ,
      ),
    read: false,
    deserialize: false,
    write: false,
    serialize: false,
  ),
  new Post(
    uriTemplate: '/users/-/reset-password',
    controller: UserPasswordReset::class,
    openapi:
      new Model\Operation(
      summary: 'Change the password for an user. If securityCode is invalid a new one will be sent.',
      requestBody:
        new Model\RequestBody(
          content: new \ArrayObject([
          'application/json' => [
            'schema' => [
              'type'       => 'object',
              'properties' => [
                'email'        => ['type' => 'string'],
                'password'     => ['type' => 'string'],
                'securityCode' => ['type' => 'string'],
              ],
            ],
          ],
        ]),
        ),
      ),
    read: false,
    deserialize: false,
    write: false,
    serialize: false
  )], normalizationContext: [
    'groups' => ['user', 'user-read'],
  ], denormalizationContext: [
    'groups' => ['user', 'user-write'],
  ],
  processor: UserProcessor::class,
)]
#[ApiFilter(SearchFilter::class, properties: ['role' => 'exact', 'memberships.member.club.uuid' => 'exact'])]
#[ApiFilter(OrderFilter::class, properties: ['lastname' => 'ASC', 'firstname' => 'ASC', 'accountActivated' => 'DESC'])]
#[ApiFilter(MultipleFilter::class, properties: ['firstname', 'lastname', 'email'])]
class User extends UuidEntity implements UserInterface, PasswordAuthenticatedUserInterface, TimestampEntityInterface {
  use TimestampTrait;

  private bool $skipAutoSetUserMember = false;

  #[Groups(['autocomplete', 'user-read', 'member-read'])]
  private ?string $fullName = null;

  #[ORM\Column] // Roles are set based on role definition
  private array $roles = [];

  #[Groups(['user'])]
  private UserRole $role = UserRole::user;

  /**
   * @var Collection<int, Profile>
   */
  #[Groups(['user', 'self-read'])]
  private Collection $linkedProfiles;

  /**
   * @var string The hashed password
   */
  #[ORM\Column(nullable: true)]
  private ?string $password = null;

  /**
   * Not in database, use for update password
   *
   * @var string|null
   */
  #[Groups(['super-admin-write'])]
  private ?string $plainPassword = null;

  #[ORM\Column]
  #[Groups(['user-read', 'super-admin-write'])]
  private bool $accountActivated = false;

  #[ORM\Column(length: 180, unique: true)]
  #[Groups(['user-read', 'super-admin-write'])]
  #[Assert\NotBlank(allowNull: false)]
  #[Assert\Email]
  private ?string $email = null;

  #[ORM\Column(length: 255, nullable: true)]
  #[Groups(['autocomplete', 'user-read', 'super-admin-write'])]
  #[Assert\NotBlank(allowNull: false)]
  private ?string $firstname = null;

  #[ORM\Column(length: 255, nullable: true)]
  #[Groups(['autocomplete', 'user-read', 'super-admin-write'])]
  #[Assert\NotBlank(allowNull: false)]
  private ?string $lastname = null;

  #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
  #[Groups(['user-read', 'super-admin-write'])]
  private ?\DateTimeImmutable $legalsAccepted = null;

  #[Groups(['user-read', 'super-admin-read'])]
  private bool $legalsExpired = true;

  /**
   * @var Collection<int, UserMember>
   */
  #[ORM\OneToMany(mappedBy: 'user', targetEntity: UserMember::class, orphanRemoval: true)]
  private Collection $memberships;

  public function __construct() {
    parent::__construct();
    $this->memberships = new ArrayCollection();
  }

  // Custom calculated fields

  /**
   * @return Collection<int, Profile>
   */
  public function getLinkedProfiles(): Collection {
    $userClubs = [];
    $multipleClubs = $this->getMemberships()->count() > 1;

    foreach ($this->getMemberships() as $membership) {
      $club = $membership?->getMember()?->getClub() ?? $membership->getBadgerClub();
      if ($club) {
        $profile = new Profile();
        $profile->setRole($membership->getRole());

        $displayName = $club->getName();
        $id = "c-" . $club->getUuid()->toString();

        if ($membership->getMember()) {
          $profile->setMember($membership->getMember());
          $id = "m-" . $membership->getMember()->getUuid()->toString();
          if ($multipleClubs) {
            $displayName .= " - " . $membership->getMember()->getFullName();
          } else {
            $displayName = $membership->getMember()->getFullName();
          }
        }

        $profile
          ->setId($id)
          ->setDisplayName($displayName)
          ->setClub($club);

        $userClubs[] = $profile;
      }
    }

    usort($userClubs, function(Profile $a, Profile $b) {
      return $a->getDisplayName() <=> $b->getDisplayName();
    });

    $this->linkedProfiles = new ArrayCollection($userClubs);

    return $this->linkedProfiles;
  }

  public function getFullName(): ?string {
    return $this->lastname . " " . $this->firstname;
  }

  public function getRole(): UserRole {
    $userRole = UserRole::tryFrom($this->getRoles()[0]);
    return $userRole ?? UserRole::user;
  }

  public function setRole(UserRole $role): static {
    return $this->setRoles([$role->value]);
  }

  // End custom calculated fields

  public function getEmail(): ?string {
    return $this->email;
  }

  public function setEmail(string $email): static {
    if (empty($email)) {
      $email = null;
    }
    $this->email = $email;
    return $this;
  }

  /**
   * A visual identifier that represents this user.
   *
   * @see UserInterface
   */
  public function getUserIdentifier(): string {
    return $this->email;
  }

  /**
   * @see UserInterface
   */
  public function getRoles(): array {
    $roles = $this->roles;
    // We ensure every real user have at least the ROLE_USER
    if (!in_array(UserRole::badger->value, $roles)) {
      $roles[] = UserRole::user->value;
    }
    return array_unique($roles);
  }

  public function setRoles(array $roles): static {
    $this->roles = $roles;
    return $this;
  }

  /**
   * @see PasswordAuthenticatedUserInterface
   */
  public function getPassword(): string {
    return $this->password;
  }

  public function setPassword(string $password): static {
    $this->password = $password;
    return $this;
  }

  public function getPlainPassword(): ?string {
    return $this->plainPassword;
  }

  public function setPlainPassword(?string $plainPassword): User {
    $this->plainPassword = $plainPassword;
    return $this;
  }

  /**
   * @see UserInterface
   */
  public function eraseCredentials(): void {
    // If you store any temporary, sensitive data on the user, clear it here
    $this->plainPassword = null;
  }

  public function getFirstname(): ?string {
    return $this->firstname;
  }

  public function setFirstname(string $firstname): static {
    $this->firstname = ucfirst($firstname);
    return $this;
  }

  public function getLastname(): ?string {
    return $this->lastname;
  }

  public function setLastname(string $lastname): static {
    $this->lastname = strtoupper($lastname);
    return $this;
  }

  public function isAccountActivated(): bool {
    return $this->accountActivated;
  }

  public function setAccountActivated(bool $accountActivated): static {
    $this->accountActivated = $accountActivated;
    return $this;
  }

  /**
   * @return Collection<int, UserMember>
   */
  public function getMemberships(): Collection {
      return $this->memberships;
  }

  public function addMembership(UserMember $membership): static {
      if (!$this->memberships->contains($membership)) {
          $this->memberships->add($membership);
          $membership->setUser($this);
      }
      return $this;
  }

  public function removeMembership(UserMember $membership): static {
      if ($this->memberships->removeElement($membership)) {
          // set the owning side to null (unless already changed)
          if ($membership->getUser() === $this) {
              $membership->setUser(null);
          }
      }
      return $this;
  }

  public function getLegalsAccepted(): ?\DateTimeImmutable {
    return $this->legalsAccepted;
  }

  public function setLegalsAccepted(?\DateTimeImmutable $legalsAccepted): User {
    $this->legalsAccepted = $legalsAccepted;
    return $this;
  }

  public function getLegalsExpired(): bool {
    return $this->legalsExpired;
  }

  public function setLegalsExpired(bool $legalsExpired): User {
    $this->legalsExpired = $legalsExpired;
    return $this;
  }

  public function isSkipAutoSetUserMember(): bool {
    return $this->skipAutoSetUserMember;
  }

  public function setSkipAutoSetUserMember(bool $skipAutoSetUserMember): User {
    $this->skipAutoSetUserMember = $skipAutoSetUserMember;
    return $this;
  }
}
