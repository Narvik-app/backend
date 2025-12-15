<?php

namespace App\Tests\Story;

use App\Tests\Factory\ClubFactory;
use App\Tests\Factory\InventoryCategoryFactory;
use Zenstruck\Foundry\Story;

final class InventoryCategoryStory extends Story {
  public const array CATEGORIES = [
    "Cibles",
    "Munitions",
    "Droit de tir",
    "Administratif",
    "Concours",
    "Autres",
  ];

  public function build(): void {
    foreach (self::CATEGORIES as $category) {
      $this->addToPool('default', InventoryCategoryFactory::createOne([
        'name' => $category,
      ]));
    }
  }
}
