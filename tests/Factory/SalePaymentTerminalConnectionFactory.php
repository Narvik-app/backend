<?php

namespace App\Tests\Factory;

use App\Entity\ClubDependent\Plugin\Sale\SalePaymentTerminalConnection;
use App\Enum\SalePaymentTerminalProvider;
use App\Tests\Story\_InitStory;

final class SalePaymentTerminalConnectionFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory {
  public function __construct() {
  }

  public static function class(): string {
    return SalePaymentTerminalConnection::class;
  }

  protected function defaults(): array|callable {
    return [
      'club' => _InitStory::club_1(),
      'name' => self::faker()->text(12),
      'provider' => SalePaymentTerminalProvider::sumup,
      'available' => true,
      'credentials' => ['apiKey' => 'sup_sk_test', 'merchantCode' => 'MERCHANT123'],
    ];
  }
}
