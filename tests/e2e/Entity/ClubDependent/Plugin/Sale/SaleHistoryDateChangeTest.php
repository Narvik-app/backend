<?php

namespace App\Tests\e2e\Entity\ClubDependent\Plugin\Sale;

use App\Tests\e2e\AbstractApiTestCase;
use App\Tests\Factory\InventoryItemFactory;
use App\Tests\Factory\InventoryItemHistoryFactory;
use App\Tests\Factory\SalePaymentModeFactory;
use App\Tests\Story\_InitStory;

/**
 * Verifies that editing a sale's date moves its linked InventoryItemHistory row
 * to the new date and adjusts intermediate rows' quantities by ±delta.
 */
class SaleHistoryDateChangeTest extends AbstractApiTestCase {
  public function initDefaultFixtures(): void {
    // No shared fixtures needed; each test builds its own controlled scenario.
  }

  /**
   * Moving a sale to an earlier date:
   * - The sale's linked history row moves to the new date.
   * - History rows strictly between the new and old dates are adjusted by -delta.
   */
  public function testMovingSaleEarlierAdjustsIntermediateRows(): void {
    $club       = _InitStory::club_1();
    $clubIri    = $this->getIriFromResource($club);
    $paymentMode = SalePaymentModeFactory::createOne();

    // Item with explicit tracking: quantity=50, sellingQuantity=1 → delta will equal sold qty
    $item    = InventoryItemFactory::createOne(['quantity' => 50, 'sellingQuantity' => 1, 'sellingPrice' => '10.00']);
    $itemIri = $this->getIriFromResource($item);

    // Create sale via API so SaleSubscriber fires and creates the linked history row
    $this->loggedAsAdminClub1();
    $saleResponse = $this->makePostRequest($clubIri . '/sales', [
      'salePurchasedItems' => [['item' => $itemIri, 'quantity' => 5]],
      'paymentMode'        => $this->getIriFromResource($paymentMode),
    ]);
    $this->assertResponseStatusCodeSame(201);
    $saleIri = $saleResponse->toArray()['@id'];

    // Item quantity is now 45 (50 - 5), a history row linked to the sale exists at ~now

    // Seed a plain history row 3 days ago with a known quantity
    $tMid = new \DateTimeImmutable('-3 days');
    InventoryItemHistoryFactory::createOne([
      'item'      => $item,
      'quantity'  => 48,
      'createdAt' => $tMid,
    ]);

    // Move the sale 7 days into the past (before the intermediate row)
    $tNew = new \DateTimeImmutable('-7 days');
    $this->makePatchRequest($saleIri, [
      'createdAt' => $tNew->format(\DateTimeInterface::ATOM),
    ]);
    $this->assertResponseIsSuccessful();

    // Fetch all raw history rows for the item
    $historiesUrl = $clubIri . "/inventory-items/{$item->getUuid()}/histories";
    $histories    = $this->makeGetRequest($historiesUrl)->toArray()['member'];

    $tNewPrefix = $tNew->format('Y-m-d');
    $tMidPrefix = $tMid->format('Y-m-d');

    $hSale = null;
    $hMid  = null;
    foreach ($histories as $row) {
      if (str_starts_with((string) $row['createdAt'], $tNewPrefix)) {
        $hSale = $row;
      }
      if (str_starts_with((string) $row['createdAt'], $tMidPrefix)) {
        $hMid = $row;
      }
    }

    // The sale's history row moved to the new date with its quantity unchanged
    $this->assertNotNull($hSale, "Expected a history row at {$tNewPrefix} after moving the sale.");
    $this->assertEquals(45, $hSale['quantity']);

    // The intermediate row was adjusted by -delta (48 - 5 = 43)
    $this->assertNotNull($hMid, "Expected the intermediate history row at {$tMidPrefix}.");
    $this->assertEquals(43, $hMid['quantity']);
  }

  /**
   * Moving a sale to a later date:
   * - The sale's linked history row moves to the new date.
   * - History rows strictly between the old and new dates are adjusted by +delta.
   */
  public function testMovingSaleLaterAdjustsIntermediateRows(): void {
    $club        = _InitStory::club_1();
    $clubIri     = $this->getIriFromResource($club);
    $paymentMode = SalePaymentModeFactory::createOne();

    $item    = InventoryItemFactory::createOne(['quantity' => 50, 'sellingQuantity' => 1, 'sellingPrice' => '10.00']);
    $itemIri = $this->getIriFromResource($item);

    // Create sale with createdAt set to 7 days ago
    $tOld = new \DateTimeImmutable('-7 days');
    $this->loggedAsAdminClub1();
    $saleResponse = $this->makePostRequest($clubIri . '/sales', [
      'salePurchasedItems' => [['item' => $itemIri, 'quantity' => 5]],
      'paymentMode'        => $this->getIriFromResource($paymentMode),
      'createdAt'          => $tOld->format(\DateTimeInterface::ATOM),
    ]);
    $this->assertResponseStatusCodeSame(201);
    $saleIri = $saleResponse->toArray()['@id'];

    // Seed an intermediate history row 3 days ago
    $tMid = new \DateTimeImmutable('-3 days');
    InventoryItemHistoryFactory::createOne([
      'item'      => $item,
      'quantity'  => 40,
      'createdAt' => $tMid,
    ]);

    // Move the sale to 1 day ago (after the intermediate row)
    $tNew = new \DateTimeImmutable('-1 day');
    $this->makePatchRequest($saleIri, [
      'createdAt' => $tNew->format(\DateTimeInterface::ATOM),
    ]);
    $this->assertResponseIsSuccessful();

    $historiesUrl = $clubIri . "/inventory-items/{$item->getUuid()}/histories";
    $histories    = $this->makeGetRequest($historiesUrl)->toArray()['member'];

    $tNewPrefix = $tNew->format('Y-m-d');
    $tMidPrefix = $tMid->format('Y-m-d');

    $hSale = null;
    $hMid  = null;
    foreach ($histories as $row) {
      if (str_starts_with((string) $row['createdAt'], $tNewPrefix)) {
        $hSale = $row;
      }
      if (str_starts_with((string) $row['createdAt'], $tMidPrefix)) {
        $hMid = $row;
      }
    }

    // The sale's history row moved to the new date
    $this->assertNotNull($hSale, "Expected a history row at {$tNewPrefix} after moving the sale.");
    $this->assertEquals(45, $hSale['quantity']);

    // The intermediate row was adjusted by +delta (40 + 5 = 45): sale had not yet happened during that period
    $this->assertNotNull($hMid, "Expected the intermediate history row at {$tMidPrefix}.");
    $this->assertEquals(45, $hMid['quantity']);
  }
}
