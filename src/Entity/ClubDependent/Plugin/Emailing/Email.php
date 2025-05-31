<?php

namespace App\Entity\ClubDependent\Plugin\Emailing;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model;
use App\Controller\ClubDependent\Plugin\Emailing\EmailSend;
use App\Entity\Abstract\UuidEntity;
use App\Entity\Club;
use App\Entity\Interface\ClubLinkedEntityInterface;
use App\Entity\Interface\TimestampEntityInterface;
use App\Entity\Trait\SelfClubLinkedEntityTrait;
use App\Entity\Trait\TimestampTrait;
use App\Enum\ClubRole;
use App\Enum\EmailStatus;
use App\Repository\ClubDependent\Plugin\Emailing\EmailRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;


#[ORM\Entity(repositoryClass: EmailRepository::class)]
#[ApiResource(uriTemplate: '/clubs/{clubUuid}/emails/{uuid}', operations: [
    new GetCollection(
      uriTemplate: '/clubs/{clubUuid}/emails.{_format}',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
      ],
      security: "is_granted('" . ClubRole::admin->value . "', request)",
    ),
    new Get(security: "is_granted('" . ClubRole::admin->value . "', object)",),
    new Patch(security: "is_granted('" . ClubRole::admin->value . "', object) && object.getStatus() === " . EmailStatus::DRAFT->value . ")",),

    new Post(
      uriTemplate: '/clubs/{clubUuid}/emails/-/send',
      uriVariables: [
        'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
      ],
      controller: EmailSend::class,
      openapi: new Model\Operation(
        requestBody: new Model\RequestBody(
          content: new \ArrayObject([
            'multipart/form-data' => [
              'schema' => [
                'type' => 'object',
                'properties' => [
                  'file' => [
                    'type' => 'string',
                    'format' => 'binary'
                  ],
                  'title' => [
                    'type' => 'string',
                  ],
                  'content' => [
                    'type' => 'string',
                  ],
                  'members' => [
                    'type' => 'string',
                    'description' => 'List of members uuid, separated by comma',
                  ],
                  'replyTo' => [
                    'type' => 'string',
                    'description' => 'Custom reply to email, otherwise fallback to the club contact email',
                  ],
                  'isNewsletter' => [
                    'type' => 'boolean',
                    'default' => true,
                    'description' => 'If set to false, the member will always receive the email. Even if he disabled it in his settings.',
                  ]
                ],
                'required' => ['file', 'title', 'content', 'members']
              ]
            ]
          ])
        )
      ),
      securityPostDenormalize: "is_granted('".ClubRole::admin->value."', request)",
      read: false,
      deserialize: false
    ),
  ], uriVariables: [
    'clubUuid' => new Link(toProperty: 'club', fromClass: Club::class),
    'uuid'     => new Link(fromClass: self::class),
  ], normalizationContext: [
    'groups' => ['email', 'email-read']
  ], denormalizationContext: [
    'groups' => ['email', 'email-write']
  ], order: ['createdAt' => 'DESC'],)]
#[ApiFilter(OrderFilter::class, properties: ['createdAt' => 'DESC'])]
class Email extends UuidEntity implements TimestampEntityInterface, ClubLinkedEntityInterface {
  use TimestampTrait;
  use SelfClubLinkedEntityTrait;

  #[ORM\Column(type: "string", enumType: EmailStatus::class)]
  #[Groups(['email'])]
  private EmailStatus $status = EmailStatus::DRAFT;

  #[ORM\Column]
  #[Groups(['email'])]
  private bool $isNewsletter = true;

  #[ORM\Column(length: 255)]
  #[Groups(['email'])]
  #[Assert\NotBlank(allowNull: false)]
  private ?string $title = null;

  #[ORM\Column(type: Types::TEXT)]
  #[Groups(['email'])]
  #[Assert\NotBlank(allowNull: false)]
  private ?string $content = null;

  #[ORM\Column]
  #[Groups(['email-read'])]
  private ?int $recipientCount = null;

  #[ORM\Column(length: 255)]
  #[Groups(['email-read'])]
  private ?string $sender = null;

  // Field not saved in the database
  // Use for sending the email

  private ?string $replyTo = null;

  private ?UploadedFile $attachment = null;

  private array $members = [];

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

  public function getRecipientCount(): ?int {
    return $this->recipientCount;
  }

  public function setRecipientCount(int $recipientCount): static {
    $this->recipientCount = $recipientCount;
    return $this;
  }

  public function getStatus(): EmailStatus {
    return $this->status;
  }

  public function setStatus(EmailStatus $status): static {
    $this->status = $status;
    return $this;
  }

  public function getSender(): ?string {
    return $this->sender;
  }

  public function setSender(string $sender): static {
    $this->sender = $sender;
    return $this;
  }

  public function getIsNewsletter(): bool {
      return $this->isNewsletter;
  }

  public function setIsNewsletter(bool $isNewsletter): static {
      $this->isNewsletter = $isNewsletter;
      return $this;
  }

  public function getReplyTo(): ?string {
      return $this->replyTo;
  }

  public function setReplyTo(?string $replyTo): static {
      $this->replyTo = $replyTo;
      return $this;
  }

  public function getAttachment(): ?UploadedFile {
    return $this->attachment;
  }

  public function setAttachment(?UploadedFile $attachment): static {
    $this->attachment = $attachment;
    return $this;
  }

  public function getMembers(): array {
    return $this->members;
  }

  public function setMembers(array $members): static {
    $this->members = $members;
    return $this;
  }

}
