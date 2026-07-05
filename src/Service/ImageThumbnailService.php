<?php

namespace App\Service;

use App\Entity\File as FileEntity;
use App\Enum\ThumbnailSize;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\Filesystem\Filesystem;

class ImageThumbnailService {

  private const array THUMBNAILABLE_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

  public function __construct(
    private readonly Filesystem $fs,
    private readonly ContainerBagInterface $params,
  ) {
  }

  public function isThumbnailable(FileEntity $file): bool {
    return in_array($file->getMimeType(), self::THUMBNAILABLE_MIME_TYPES, true);
  }

  /**
   * Returns the absolute path to a cached WebP derivative of the given file, generating it on
   * first request. Returns null when the file isn't a supported raster image or the original
   * can't be found/decoded.
   */
  public function getOrCreateThumbnailPath(FileEntity $file, ThumbnailSize $size): ?string {
    if (!$this->isThumbnailable($file)) {
      return null;
    }

    $originalPath = $this->params->get('app.files') . '/' . $file->getPath();
    if (!$this->fs->exists($originalPath)) {
      return null;
    }

    $cacheDir = $this->params->get('app.image_cache');
    if (!$this->fs->exists($cacheDir)) {
      $this->fs->mkdir($cacheDir);
    }
    $cachePath = $cacheDir . '/' . $file->getUuid()->toString() . '-' . $size->value . '.webp';

    if ($this->fs->exists($cachePath)) {
      return $cachePath;
    }

    return $this->generateThumbnail($originalPath, $cachePath, $file->getMimeType(), $size);
  }

  private function generateThumbnail(string $originalPath, string $cachePath, string $mimeType, ThumbnailSize $size): ?string {
    $source = match ($mimeType) {
      'image/jpeg' => @imagecreatefromjpeg($originalPath),
      'image/png' => @imagecreatefrompng($originalPath),
      'image/webp' => @imagecreatefromwebp($originalPath),
      default => false,
    };

    if (!$source instanceof \GdImage) {
      return null;
    }

    try {
      $srcWidth = imagesx($source);
      $srcHeight = imagesy($source);
      $maxEdge = $size->maxEdge();

      // Downscale-only: never upscale a smaller original.
      $ratio = min(1, $maxEdge / max($srcWidth, $srcHeight));
      $dstWidth = max(1, (int) round($srcWidth * $ratio));
      $dstHeight = max(1, (int) round($srcHeight * $ratio));

      $dest = imagecreatetruecolor($dstWidth, $dstHeight);

      // Preserve transparency (PNG/WebP alpha channel).
      imagealphablending($dest, false);
      imagesavealpha($dest, true);
      $transparent = imagecolorallocatealpha($dest, 0, 0, 0, 127);
      imagefilledrectangle($dest, 0, 0, $dstWidth, $dstHeight, $transparent);

      imagecopyresampled($dest, $source, 0, 0, 0, 0, $dstWidth, $dstHeight, $srcWidth, $srcHeight);

      try {
        $tmpPath = $cachePath . '.' . bin2hex(random_bytes(8)) . '.tmp';
        if (!imagewebp($dest, $tmpPath, 80)) {
          return null;
        }
        // Atomic swap so concurrent first-requests never read a partial file.
        $this->fs->rename($tmpPath, $cachePath, true);
      } finally {
        imagedestroy($dest);
      }

      return $cachePath;
    } finally {
      imagedestroy($source);
    }
  }
}
