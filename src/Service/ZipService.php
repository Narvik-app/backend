<?php

namespace App\Service;

use App\Entity\Club;
use App\Entity\File as FileEntity;
use App\Enum\FileCategory;
use Symfony\Component\HttpFoundation\File\File as SfFile;

/**
 * Bundles a set of already-stored Files into a single zip archive, persisted through FileService
 * like any other file — so it gets the same storage layout, url generation, etc.
 */
class ZipService {
  public function __construct(
    private readonly FileService $fileService,
  ) {
  }

  /**
   * @param FileEntity[] $files Files to bundle. Entries whose content is missing on disk are
   *                            silently skipped (they may have been removed independently).
   * @return FileEntity|null The persisted zip, or null if none of the given files could be read.
   */
  public function createZip(array $files, string $filename, FileCategory $category, bool $isPublic = false, ?Club $club = null, bool $flush = true): ?FileEntity {
    $tmpPath = tempnam(sys_get_temp_dir(), 'zip_') . '.zip';

    $zip = new \ZipArchive();
    $zip->open($tmpPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

    $usedEntryNames = [];
    foreach ($files as $file) {
      $path = $this->fileService->getAbsolutePath($file);
      if (!$path) {
        continue;
      }

      $zip->addFile($path, $this->uniqueEntryName($file, $usedEntryNames));
    }

    $entryCount = $zip->numFiles;
    $zip->close();

    if ($entryCount === 0) {
      unlink($tmpPath);
      return null;
    }

    return $this->fileService->importFile(new SfFile($tmpPath), $filename, $category, $isPublic, $club, $flush);
  }

  /** Disambiguates entries so two attached files sharing the same display name don't collide inside the archive. */
  private function uniqueEntryName(FileEntity $file, array &$usedEntryNames): string {
    $name = $file->getFilename() ?? $file->getUuid()->toString();

    $entryName = $name;
    $suffix = 2;
    while (isset($usedEntryNames[$entryName])) {
      $extension = pathinfo($name, PATHINFO_EXTENSION);
      $base = $extension ? substr($name, 0, -(strlen($extension) + 1)) : $name;
      $entryName = $extension ? "{$base} ({$suffix}).{$extension}" : "{$name} ({$suffix})";
      $suffix++;
    }

    $usedEntryNames[$entryName] = true;
    return $entryName;
  }
}
