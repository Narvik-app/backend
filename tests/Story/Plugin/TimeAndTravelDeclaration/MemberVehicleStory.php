<?php

namespace App\Tests\Story\Plugin\TimeAndTravelDeclaration;

use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\MemberVehicle;
use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\VehicleEngineType;
use App\Tests\Factory\ClubDependent\Plugin\TimeAndTravelDeclaration\MemberVehicleFactory;
use App\Tests\Story\_InitStory;
use Zenstruck\Foundry\Story;

/**
 * @method static MemberVehicle basic()
 * @method static MemberVehicle electric()
 * @method static MemberVehicle diesel()
 */
final class MemberVehicleStory extends Story {

  public function build(): void {
    // Create some common member vehicles
    $this->addState('basic', MemberVehicleFactory::createOne([
      'member' => _InitStory::MEMBER_member_club_1(),
      'brand' => 'Renault',
      'model' => 'Clio',
      'licensePlate' => 'AB-123-CD',
      'engineType' => VehicleEngineType::PETROL,
      'fiscalPower' => 6,
      'fiscalCoefficient' => '0.5000',
    ]), 'vehicles');

    $this->addState('electric', MemberVehicleFactory::createOne([
      'member' => _InitStory::MEMBER_member_club_1(),
      'brand' => 'Tesla',
      'model' => 'Model 3',
      'licensePlate' => 'EV-456-FG',
      'engineType' => VehicleEngineType::ELECTRIC,
      'fiscalPower' => 4,
      'fiscalCoefficient' => '0.2500',
    ]), 'vehicles');

    $this->addState('diesel', MemberVehicleFactory::createOne([
      'member' => _InitStory::MEMBER_member_club_1(),
      'brand' => 'Peugeot',
      'model' => '3008',
      'licensePlate' => 'DI-789-HI',
      'engineType' => VehicleEngineType::DIESEL,
      'fiscalPower' => 10,
      'fiscalCoefficient' => '0.7500',
    ]), 'vehicles');
  }
}
