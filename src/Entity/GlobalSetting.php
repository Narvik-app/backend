<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model;
use App\Controller\GlobalSettingGetPublic;
use App\Controller\GlobalSettingImportLogo;
use App\Controller\GlobalSettingLegals;
use App\Controller\GlobalSettingLegalsFileUpload;
use App\Controller\GlobalSettingSmtp;
use App\Controller\GlobalSettingTestEmail;
use App\Enum\UserRole;
use App\Repository\GlobalSettingRepository;
use App\State\GlobalSettingProcessor;
use App\State\GlobalSettingProvider;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity(repositoryClass: GlobalSettingRepository::class)]
#[ApiResource( // GlobalSetting are only available for super admin
  operations: [
    new GetCollection(),
    new Get(),
    new Patch(),

    new Get(
      uriTemplate: '/public/global-settings/{name}',
      controller: GlobalSettingGetPublic::class,
    ),

    new Post(
      uriTemplate: '/global-settings/-/test-email',
      controller: GlobalSettingTestEmail::class,
      openapi: new Model\Operation(
        requestBody: new Model\RequestBody(
          content: new \ArrayObject([
            'application/json' => [
              'schema' => [
                'type' => 'object',
                'properties' => [
                  'to' => ['type' => 'string'],
                ]
              ]
            ]
          ])
        )
      ),
      security: "is_granted('".UserRole::super_admin->value."')",
      deserialize: false,
    ),

    new Post(
      uriTemplate: '/global-settings/-/legals',
      controller: GlobalSettingLegals::class,
      openapi: new Model\Operation(
        requestBody: new Model\RequestBody(
          content: new \ArrayObject([
            'application/json' => [
              'schema' => [
                'type' => 'object',
                'properties' => [
                  'date' => ['type' => 'string'],
                ]
              ]
            ]
          ])
        )
      ),
      security: "is_granted('".UserRole::super_admin->value."')",
      deserialize: false,
    ),
    new Post(
      uriTemplate: '/global-settings/-/legals-file',
      controller: GlobalSettingLegalsFileUpload::class,
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
                  'type' => [
                    'type' => 'string',
                  ]
                ]
              ]
            ]
          ])
        )
      ),
      security: "is_granted('".UserRole::super_admin->value."')",
      deserialize: false,
    ),

    new Post(
      uriTemplate: '/global-settings/-/smtp',
      controller: GlobalSettingSmtp::class,
      openapi: new Model\Operation(
        requestBody: new Model\RequestBody(
          content: new \ArrayObject([
            'application/json' => [
              'schema' => [
                'type' => 'object',
                'properties' => [
                  'on'                => ['type' => 'string'],
                  'host'              => ['type' => 'string'],
                  'port'              => ['type' => 'string'],
                  'username'          => ['type' => 'string'],
                  'password'          => ['type' => 'string'],
                  'sender'            => ['type' => 'string'],
                  'newsletterSender'  => ['type' => 'string'],
                  'senderName'        => ['type' => 'string'],
                ]
              ]
            ]
          ])
        )
      ),
      security: "is_granted('".UserRole::super_admin->value."')",
      deserialize: false,
    ),
  ],
  provider: GlobalSettingProvider::class,
  processor: GlobalSettingProcessor::class,
)]
class GlobalSetting {
  #[ORM\Id]
  #[ORM\GeneratedValue(strategy: 'SEQUENCE')]
  #[ORM\Column]
  #[ApiProperty(identifier: false)]
  private ?int $id = null;

  #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255)]
  #[ApiProperty(identifier: true)]
  private string $name;

  #[ORM\Column(type: \Doctrine\DBAL\Types\Types::TEXT, nullable: true)]
  private string|null $value = null;

  /**
   * Not persisted: set explicitly by GlobalSettingProvider so the API can
   * tell "a value is configured" apart from "no value" even when isSecret()
   * masks $value to null on read.
   */
  private ?bool $hasValue = null;

  public function getId(): ?int {
    return $this->id;
  }

  public function getName(): string {
    return $this->name;
  }

  public function setName(string $name): static {
    $this->name = $name;
    return $this;
  }

  public function getValue(): ?string {
    return $this->value;
  }

  public function setValue(?string $value): static {
    $this->value = $value;
    return $this;
  }

  public function getHasValue(): bool {
    return $this->hasValue ?? ($this->value !== null);
  }

  public function setHasValue(bool $hasValue): static {
    $this->hasValue = $hasValue;
    return $this;
  }
}
