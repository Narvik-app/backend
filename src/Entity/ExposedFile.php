<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\State\ExposedFileProvider;
use Symfony\Component\Serializer\Attribute\Groups;


#[ApiResource(
  operations: [
    new Get(
      uriTemplate: '/files/{id}',
    ),

    new Get(
      uriTemplate: '/public/files/{id}',
      name: 'public_file'
    ),
    new Get(
      uriTemplate: '/public/files/inline/{id}',
      name: 'inline_public_file'
    ),

    new Get(
      uriTemplate: '/files/{id}/thumbnail',
      name: 'thumbnail_file'
    ),
    new Get(
      uriTemplate: '/public/files/{id}/thumbnail',
      name: 'public_image_thumbnail_file'
    ),
    new Get(
      uriTemplate: '/public/files/inline/{id}/thumbnail',
      name: 'inline_public_image_thumbnail_file'
    ),
  ],
  normalizationContext: [
    'groups' => ['exposed-file']
  ],
  provider: ExposedFileProvider::class,
)]
class ExposedFile {

  #[ApiProperty(identifier: true)]
  #[Groups(['exposed-file'])]
  private string $id; // UUID of File

  #[Groups(['exposed-file'])]
  private string $name;

  #[Groups(['exposed-file'])]
  private string $base64;

  #[Groups(['exposed-file'])]
  private string $mimeType;

  private string $path;

  public function getId(): string {
    return $this->id;
  }

  public function setId(string $id): ExposedFile {
    $this->id = $id;
    return $this;
  }

  public function getName(): string {
    return $this->name;
  }

  public function setName(string $name): ExposedFile {
    $this->name = $name;
    return $this;
  }

  public function getBase64(): string {
    return $this->base64;
  }

  public function setBase64(string $base64): ExposedFile {
    $this->base64 = $base64;
    return $this;
  }

  public function getMimeType(): string {
    return $this->mimeType;
  }

  public function setMimeType(string $mimeType): ExposedFile {
    $this->mimeType = $mimeType;
    return $this;
  }

  public function getPath(): string {
    return $this->path;
  }

  public function setPath(string $path): ExposedFile {
    $this->path = $path;
    return $this;
  }

}
