<?php

namespace App\Service;

use App\Entity\Club;
use App\Enum\FileCategory;
use App\Repository\ClubDependent\MemberRepository;
use App\Repository\FileRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\File\File as SfFile;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Mime\Part\File;


class ImageService {

  public function __construct(
    private readonly Filesystem $fs,
    private readonly ContainerBagInterface $params,
    private readonly FileRepository $fileRepository,
    private readonly MemberRepository $memberRepository,
    private readonly FileService $fileService,
    private readonly EntityManagerInterface $entityManager,
  ) {
  }

  public function getLogoFile(bool $white = false): ?File {
    $path = $this->params->get('app.public_images');
    if ($white) {
      $path .= '/logo-narvik-white.png';
    } else {
      $path .= '/logo-narvik.png';
    }

    if (!$this->fs->exists($path)) {
      return null;
    }

    return new File($path);
  }

  public function getClubLogoFile(Club $club): ?File {
    $logoPath = $club->getSettings()?->getLogo();
    if (!$logoPath || !$logoPath->getPath()) {
      return null;
    }

    $path = $this->params->get('app.public_images') . $logoPath->getFilename();

    if (!$this->fs->exists($path)) {
      return null;
    }

    return new File($path);
  }

  public function importClubLogo(Club $club, ?UploadedFile $file): void {
    $clubSettings = $club->getSettings();
    if (!$clubSettings) return;

    // We remove all old profile images
    $oldPictures = $this->fileRepository->findByClubAndCategory($club, FileCategory::logo);
    foreach ($oldPictures as $oldPicture) {
      $this->entityManager->remove($oldPicture);
    }

    if (!$file) {
      $dbFile = null;
    } else {
      $dbFile = $this->fileService->importFile($file, $file->getFilename(), FileCategory::logo, isPublic: true, club: $club, flush: false);
    }

    $clubSettings->setLogo($dbFile);
    $this->entityManager->persist($clubSettings);

    $this->entityManager->flush();
  }

  public function importItacPhotos(Club $club, UploadedFile $file): void {
    // We remove all old profile images
    $oldPictures = $this->fileRepository->findByClubAndCategory($club, FileCategory::member_picture);
    foreach ($oldPictures as $oldPicture) {
      $this->entityManager->remove($oldPicture);
    }

    $fileFolder = $this->params->get('app.files');
    $tmpFolder = $fileFolder . '/tmp_zip_itac_photos_' . UuidService::generateUuid();
    $this->fileService->createFolderIfNotExist($tmpFolder);

    // We import from the zip
    $zipArchive = new \ZipArchive();
    $zipArchive->open($file->getRealPath());
    $zipArchive->extractTo($tmpFolder);
    $zipArchive->close();

    $finder = new Finder();
    $finder->files()->in($tmpFolder);
    if (!$finder->hasResults()) {
      return;
    }

    foreach ($finder as $findFile) {
      // We only import for match member
      $licence = explode('.', $findFile->getFilename(), 2)[0];
      if (empty($licence)) {
        continue;
      }
      $member = $this->memberRepository->findOneByLicence($club, $licence);
      if (!$member) {
        continue;
      }

      $uploadedFile = new SfFile($findFile->getRealPath());
      $dbFile = $this->fileService->importFile($uploadedFile, $findFile->getFilename(), FileCategory::member_picture, club: $club, flush: false);

      $member->setProfileImage($dbFile);
      $this->entityManager->persist($member);
    }

    $this->entityManager->flush();
    $this->fileService->removeFolder($tmpFolder);
  }
}
