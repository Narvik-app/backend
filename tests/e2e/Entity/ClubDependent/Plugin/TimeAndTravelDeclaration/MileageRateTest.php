<?php

namespace App\Tests\e2e\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration;

use App\Tests\e2e\AbstractApiTestCase;
use App\Tests\Story\_InitStory;
use App\Tests\Story\MileageRateStory;

/**
 * The official mileage scale is global reference data: readable by anyone, writable only by a
 * super admin (mirrors AgeCategory) since it's the same national schedule for every club.
 */
class MileageRateTest extends AbstractApiTestCase {
  public function initDefaultFixtures(): void {
    MileageRateStory::load();
  }

  public function testAnyLoggedInUserCanReadTheScale(): void {
    $this->loggedAsMemberClub1();
    $response = $this->makeGetRequest('/mileage-rates');
    $this->assertResponseIsSuccessful();
    $this->assertEquals(27, $response->toArray()['totalItems']);
  }

  public function testOnlySuperAdminCanWriteTheScale(): void {
    $payload = [
      'category' => 'car',
      'minFiscalPower' => 8,
      'maxFiscalPower' => 8,
      'tierOrder' => 1,
      'tierMaxKm' => 5000,
      'rate' => '0.700',
      'addend' => '0.00',
    ];

    $this->loggedAsAdminClub1();
    $this->makePostRequest('/mileage-rates', $payload);
    $this->assertResponseStatusCodeSame(403);

    $this->loggedAsSuperAdmin();
    $response = $this->makePostRequest('/mileage-rates', $payload);
    $this->assertResponseStatusCodeSame(201);

    $iri = $response->toArray()['@id'];

    $this->loggedAsAdminClub1();
    $this->makePatchRequest($iri, ['rate' => '0.999']);
    $this->assertResponseStatusCodeSame(403);
    $this->makeDeleteRequest($iri);
    $this->assertResponseStatusCodeSame(403);

    $this->loggedAsSuperAdmin();
    $this->makeDeleteRequest($iri);
    $this->assertResponseIsSuccessful();
  }
}
