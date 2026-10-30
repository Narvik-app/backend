<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\State\ConfigProvider;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
  operations: [
    new Get(
      uriTemplate: '/public/config',
      name: 'config',
    ),
  ],
  normalizationContext: [
    'groups' => ['config'],
  ],
  provider: ConfigProvider::class,
)]
class Config {

  #[ApiProperty(identifier: true)]
  #[Groups(['config'])]
  private string $id = 'default'; // default or user specific

  #[Groups(['config'])]
  private ?string $appVersion = null;

  #[Groups(['config'])]
  private ?string $logo = null;

  #[Groups(['config'])]
  private ?string $logoWhite = null;

  #[Groups(['config'])]
  private ?array $modules = null;

  public function getId(): string {
    return $this->id;
  }

  public function setId(string $id): Config {
    $this->id = $id;
    return $this;
  }

  public function getAppVersion(): ?string {
    return $this->appVersion;
  }

  public function setAppVersion(?string $appVersion): Config {
    $this->appVersion = $appVersion;
    return $this;
  }

  public function getLogo(): ?string {
    return $this->logo;
  }

  public function setLogo(?string $logo): Config {
    $this->logo = $logo;
    return $this;
  }

  public function getLogoWhite(): ?string {
    return $this->logoWhite;
  }

  public function setLogoWhite(?string $logoWhite): Config {
    $this->logoWhite = $logoWhite;
    return $this;
  }

  public function getModules(): ?array {
    return $this->modules;
  }

  public function setModules(?array $modules): Config {
    $this->modules = $modules;
    return $this;
  }

  public function addModule(string $name, array $moduleConfig): Config {
    $this->modules[$name] = $moduleConfig;
    return $this;
  }
}
