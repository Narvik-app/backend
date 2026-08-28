<?php

namespace App\Tests;

use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FixtureFileManager {
  const string LOGO = 'logo-narvik.png';
  const string PDF = 'file.pdf';
  const string PROFILE_PICTURES = 'profile-pictures.zip';
  const string EDEN_MEMBERS = 'eden-members.xlsx';
  const string ITAC_MEMBERS = 'itac-members.csv';
  const string ITAC_SECONDARY_MEMBERS = 'itac-secondary-members.csv';
  const string PRESENCES_NARVIK = 'presences-narvik.csv';
  const string EXTERNAL_PRESENCES_NARVIK = 'external-presences-narvik.csv';
  const string PRESENCES_CERBERE = 'presences-cerbere.xls';
  const string NARVIK_SALES = 'narvik-sales.csv';
  const string NARVIK_INVENTORIES = 'narvik-inventories.csv';

  /**
   * @param string $filename
   * @param bool $tmpCopy When set to true, the file must be deleted at the end of the test, otherwise it will remain (i.e import that store the file and not just read it). When the upload move the file, it won't remain in the tmp folder, no need to remove it.
   * @return UploadedFile
   */
  public static function getUploadedFile(string $filename, bool $tmpCopy = false): UploadedFile {
    $file = new File(__DIR__ . '/fixtures/' . $filename);
    $path = $file->getPathname();

    if ($tmpCopy) {
      // Unique per call: parallel test runs (paratest) would otherwise race on the same shared path.
      $path = sys_get_temp_dir() . '/' . uniqid('', true) . '-' . $filename;
      file_put_contents($path, $file->getContent());
    }

    return new UploadedFile($path, $filename);
  }

  public static function removeUploadedFile(UploadedFile $file): void {
    if (file_exists($file->getPathname())) {
      unlink($file->getPathname());
    }
  }
}
