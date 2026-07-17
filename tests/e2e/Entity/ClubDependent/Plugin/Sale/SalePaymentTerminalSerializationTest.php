<?php

namespace App\Tests\e2e\Entity\ClubDependent\Plugin\Sale;

use App\Tests\e2e\AbstractApiTestCase;
use App\Tests\Factory\SalePaymentModeFactory;
use App\Tests\Factory\SalePaymentTerminalFactory;

/**
 * Regression test for the payment-mode -> terminal embedding used by the POS
 * checkout picker (new.vue filters on `paymentTerminals[].usable`). This only
 * works if the shared "sale-read" group is present on the fields the picker
 * needs (name, icon, description, available, usable) — without it, API
 * Platform serializes the relation without those fields and the checkout
 * flow silently treats every terminal as unusable.
 */
class SalePaymentTerminalSerializationTest extends AbstractApiTestCase {
  public function testPaymentModeExposesUsableTerminalFields(): void {
    $this->loggedAsAdminClub1();

    $mode = SalePaymentModeFactory::createOne();
    $terminal = SalePaymentTerminalFactory::createOne([
      'paymentMode' => $mode,
      'available' => true,
      'icon' => 'credit-card',
      'description' => 'Caisse principale',
    ]);

    $response = $this->makeGetRequest($this->getIriFromResource($mode));
    $this->assertResponseIsSuccessful();

    $data = $response->toArray();
    $this->assertArrayHasKey('paymentTerminals', $data);
    $this->assertCount(1, $data['paymentTerminals']);

    $embedded = $data['paymentTerminals'][0];
    $this->assertSame($terminal->getName(), $embedded['name']);
    $this->assertSame('credit-card', $embedded['icon']);
    $this->assertSame('Caisse principale', $embedded['description']);
    $this->assertArrayHasKey('available', $embedded);
    $this->assertTrue($embedded['available']);
    $this->assertArrayHasKey('usable', $embedded);
    $this->assertTrue($embedded['usable']);
  }
}
