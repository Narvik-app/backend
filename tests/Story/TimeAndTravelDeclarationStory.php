<?php

namespace App\Tests\Story;

use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\MemberVehicle;
use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelDeclaration;
use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\VehicleEngineType;
use App\Tests\Factory\ClubDependent\Plugin\TimeAndTravelDeclaration\MemberVehicleFactory;
use App\Tests\Factory\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelDeclarationFactory;
use Zenstruck\Foundry\Story;

/**
 * @method static MemberVehicle member_vehicle_basic()
 * @method static MemberVehicle member_vehicle_electric()
 * @method static MemberVehicle member_vehicle_diesel()
 * @method static TimeAndTravelDeclaration time_and_travel_declaration_basic()
 * @method static TimeAndTravelDeclaration time_and_travel_declaration_round_trip()
 * @method static TimeAndTravelDeclaration time_and_travel_declaration_long_distance()
 */
final class TimeAndTravelDeclarationStory extends Story {

  public function build(): void {
    // Create some common member vehicles
    $this->addState('member_vehicle_basic', MemberVehicleFactory::createOne([
      'member' => _InitStory::MEMBER_member_club_1(),
      'brand' => 'Renault',
      'model' => 'Clio',
      'licensePlate' => 'AB-123-CD',
      'engineType' => VehicleEngineType::PETROL,
      'fiscalPower' => 6,
      'fiscalCoefficient' => '0.5000',
    ]), 'vehicles');

    $this->addState('member_vehicle_electric', MemberVehicleFactory::createOne([
      'member' => _InitStory::MEMBER_member_club_1(),
      'brand' => 'Tesla',
      'model' => 'Model 3',
      'licensePlate' => 'EV-456-FG',
      'engineType' => VehicleEngineType::ELECTRIC,
      'fiscalPower' => 4,
      'fiscalCoefficient' => '0.2500',
    ]), 'vehicles');

    $this->addState('member_vehicle_diesel', MemberVehicleFactory::createOne([
      'member' => _InitStory::MEMBER_member_club_1(),
      'brand' => 'Peugeot',
      'model' => '3008',
      'licensePlate' => 'DI-789-HI',
      'engineType' => VehicleEngineType::DIESEL,
      'fiscalPower' => 10,
      'fiscalCoefficient' => '0.7500',
    ]), 'vehicles');

    // Create some common time and travel declarations
    $this->addState('time_and_travel_declaration_basic', TimeAndTravelDeclarationFactory::createOne([
      'member' => _InitStory::MEMBER_member_club_1(),
      'departureLocation' => 'Paris',
      'arrivalLocation' => 'Lyon',
      'kilometers' => 500,
      'hours' => '5.50',
      'description' => 'Déplacement compétition',
      'isRoundtrip' => true,
      'memberVehicle' => $this->member_vehicle_basic(),
    ]), 'declarations');

    $this->addState('time_and_travel_declaration_round_trip', TimeAndTravelDeclarationFactory::createOne([
      'member' => _InitStory::MEMBER_member_club_1(),
      'departureLocation' => 'Marseille',
      'arrivalLocation' => 'Nice',
      'kilometers' => 300,
      'hours' => '3.00',
      'description' => 'Formation régionale',
      'isRoundtrip' => true,
      'memberVehicle' => $this->member_vehicle_electric(),
    ]), 'declarations');

    $this->addState('time_and_travel_declaration_long_distance', TimeAndTravelDeclarationFactory::createOne([
      'member' => _InitStory::MEMBER_member_club_1(),
      'departureLocation' => 'Lille',
      'arrivalLocation' => 'Toulouse',
      'kilometers' => 1200,
      'hours' => '12.00',
      'description' => 'Finale nationale',
      'isRoundtrip' => true,
      'memberVehicle' => $this->member_vehicle_diesel(),
    ]), 'declarations');
  }
}
