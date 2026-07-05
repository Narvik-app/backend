<?php

namespace App\Service;

use App\Entity\Club;
use App\Entity\ExposedFile;
use App\Entity\File as FileEntity;
use App\Enum\ThumbnailSize;
use App\Repository\FileRepository;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\HttpFoundation\File\File as SfFile;
use App\Enum\FileCategory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Mime\Part\File as MimePartFile;

class FileService {

  public function __construct(
    private readonly Filesystem $fs,
    private readonly FileRepository $fileRepository,
    private readonly ContainerBagInterface $params,
    private readonly EntityManagerInterface $entityManager,
    private readonly ImageThumbnailService $imageThumbnailService,
  ) {
  }

  public function isThumbnailable(FileEntity $file): bool {
    return $this->imageThumbnailService->isThumbnailable($file);
  }


  public function createFolderIfNotExist(string $path): void {
    if (!$this->fs->exists($path)) {
      mkdir($path, recursive: true);
    }
  }

  public function removeFolder(string $path): void {
    if ($this->fs->exists($path)) {
      $this->fs->remove($path);
    }
  }

  public function remove(FileEntity $file): void {
    $filesFolder = $this->params->get('app.files');
    $path = $filesFolder . '/' . $file->getPath();
    if ($this->fs->exists($path)) {
      $this->fs->remove($path);
    }
  }

  public function importFile(SfFile $file, string $filename, FileCategory $fileCategory, bool $isPublic = false, ?Club $club = null, bool $flush = true): FileEntity {
    $filesFolder = $this->params->get('app.files');
    $path = '';

    if ($club && $club->getUuid()) {
      $path .= '/clubs/' . $club->getUuid()->toString() . '/';
    } else {
      $path .= '/generic/';
    }
    $filesFolder .= $path;


    $this->createFolderIfNotExist($filesFolder);
    $fileSavedName = $this->getUniqueFilename($file, $path);
    $extension = $file->getExtension();
    if (!empty($extension)) {
      $fileSavedName .= "." . $file->getExtension();
    }

    $mimeType = $file->getMimeType();

    // We move the file
    $file->move($filesFolder, $fileSavedName);

    $fileEntity = new FileEntity();
    $fileEntity
      ->setPath($path . $fileSavedName)
      ->setFilename($filename)
      ->setCategory($fileCategory)
      ->setMimeType($mimeType)
      ->setisPublic($isPublic)
      ->setClub($club);

    $this->entityManager->persist($fileEntity);

    if ($flush) {
      $this->entityManager->flush();
    }

    return $fileEntity;
  }

  public function decodeEncodedUriId(string $encodedId): UuidInterface {
    return UuidService::fromReadable($encodedId);
  }

  public function generateFileLinks(FileEntity $file): void {
    $fileId = $file->getEncodedUuid();

    if ($file->getIsPublic()) {
      $file->setPublicUrl("/public/files/$fileId");
      $file->setPublicInlineUrl("/public/files/inline/$fileId");
    }

    $file->setPrivateUrl("/files/$fileId");

    if ($this->isThumbnailable($file)) {
      if ($file->getIsPublic()) {
        $file->setPublicThumbnailUrl("/public/files/$fileId/thumbnail");
        $file->setPublicInlineThumbnailUrl("/public/files/inline/$fileId/thumbnail");
      }
      $file->setPrivateThumbnailUrl("/files/$fileId/thumbnail");
    }
  }

  /**
   * This format is used for email sending
   *
   * @param FileEntity $file
   * @return MimePartFile|null
   * @throws \Psr\Container\ContainerExceptionInterface
   * @throws \Psr\Container\NotFoundExceptionInterface
   */
  public function getMimePartFile(FileEntity $file): ?MimePartFile {
    $filesFolder = $this->params->get('app.files');
    $path = "$filesFolder/{$file->getPath()}";

    if (!$this->fs->exists($path)) {
      return null;
    }

    return new MimePartFile($path);
  }

  public function loadFileFromProtectedPath(string $publicId, bool $isInline = false): ?ExposedFile {
    $uuid = $this->decodeEncodedUriId($publicId);
    $file = $this->fileRepository->findOneByUuid($uuid->toString());
    if (!$file instanceof FileEntity) {
      return null;
    }

    return $this->loadFileFromFile($file, $isInline);
  }

  public function loadFileFromPublicPath(string $publicId, bool $isInline = false): ?ExposedFile {
    $uuid = $this->decodeEncodedUriId($publicId);
    $file = $this->fileRepository->findOneByUuid($uuid->toString());
    if (!$file instanceof FileEntity || !$file->getIsPublic()) {
      return null;
    }

    return $this->loadFileFromFile($file, $isInline);
  }

  public function loadThumbnailFromProtectedPath(string $publicId, ThumbnailSize $size, bool $isInline = false): ?ExposedFile {
    $uuid = $this->decodeEncodedUriId($publicId);
    $file = $this->fileRepository->findOneByUuid($uuid->toString());
    if (!$file instanceof FileEntity) {
      return null;
    }

    return $this->loadThumbnailFromFile($file, $size, $isInline);
  }

  public function loadThumbnailFromPublicPath(string $publicId, ThumbnailSize $size, bool $isInline = false): ?ExposedFile {
    $uuid = $this->decodeEncodedUriId($publicId);
    $file = $this->fileRepository->findOneByUuid($uuid->toString());
    if (!$file instanceof FileEntity || !$file->getIsPublic()) {
      return null;
    }

    return $this->loadThumbnailFromFile($file, $size, $isInline);
  }

  /**
   * Serves a cached WebP derivative of $file, generating it on first request. Falls back to the
   * original (same behavior as loadFileFromFile) when the file isn't a supported raster image.
   */
  private function loadThumbnailFromFile(FileEntity $file, ThumbnailSize $size, bool $isInline = false): ?ExposedFile {
    $thumbnailPath = $this->imageThumbnailService->getOrCreateThumbnailPath($file, $size);
    if (!$thumbnailPath) {
      return $this->loadFileFromFile($file, $isInline);
    }

    $image = new ExposedFile();
    $image->setId(UuidService::encodeToReadable($file->getUuid()))
          ->setName($file->getFilename())
          ->setPath($thumbnailPath);

    if (!$isInline) {
      $this->setDataUri($thumbnailPath, $image);
    }

    return $image;
  }

  private function loadFileFromFile(FileEntity $file, bool $isInline = false): ?ExposedFile {
    $filesFolder = $this->params->get('app.files');
    $path = "$filesFolder/{$file->getPath()}";

    if ($this->fs->exists($path)) {
      $image = new ExposedFile();
      $image->setId(UuidService::encodeToReadable($file->getUuid()))
            ->setName($file->getFilename())
            ->setPath($path);

      if (!$isInline) {
        $this->setDataUri($path, $image);
      }

      return $image;
    }
    return null;
  }

  private function setDataUri($imagePath, ExposedFile $image): void {
    $finfo = new \finfo(FILEINFO_MIME_TYPE);
    $type = $finfo->file($imagePath);

    $data = "data:$type;base64," . base64_encode(file_get_contents($imagePath));
    $image->setMimeType($type)
          ->setBase64($data);
  }

  private function getUniqueFilename(SfFile $file, string $path): string {
    $uniqueFilename = UuidService::encodeToReadable(UuidService::generateUuid());
    if ($this->fs->exists($path . $uniqueFilename)) {
      return $this->getUniqueFilename($file, $path);
    }
    return $uniqueFilename;
  }
}
