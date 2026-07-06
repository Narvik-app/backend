<?php

namespace App\Tests\Factory;

use App\Entity\File;
use App\Enum\FileCategory;
use App\Tests\Story\_InitStory;
use Zenstruck\Foundry\Persistence\Proxy;

/**
 * @method File|Proxy create(array|callable $attributes = [])
 * @method static File|Proxy createOne(array $attributes = [])
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<File>
 */
final class FileFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory {
  public static function class(): string {
    return File::class;
  }

  protected function defaults(): array {
    return [
      'club' => _InitStory::club_1(),
      'category' => FileCategory::loan_item_picture,
      'filename' => self::faker()->word() . '.jpg',
      'path' => '/files/' . self::faker()->uuid() . '.jpg',
      'mimeType' => 'image/jpeg',
      'isPublic' => false,
      'privateUrl' => 'https://example.test/private/' . self::faker()->uuid(),
    ];
  }
}
