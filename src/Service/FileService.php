<?php

namespace App\Service;

use App\Entity\Club;
use App\Entity\ExposedFile;
use App\Entity\File as FileEntity;
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
  ) {
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
