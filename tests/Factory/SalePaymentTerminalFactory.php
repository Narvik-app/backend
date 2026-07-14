<?php

namespace App\Tests\Factory;

use App\Entity\ClubDependent\Plugin\Sale\SalePaymentTerminal;
use App\Tests\Story\_InitStory;

final class SalePaymentTerminalFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory {
  public function __construct() {
  }

  public static function class(): string {
    return SalePaymentTerminal::class;
  }

  protected function defaults(): array|callable {
    return [
      'club' => _InitStory::club_1(),
      'name' => self::faker()->text(12),
      'connection' => SalePaymentTerminalConnectionFactory::new(),
      'externalDeviceId' => self::faker()->uuid(),
      'available' => true,
    ];
  }
}
