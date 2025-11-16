<?php

namespace App\Tests\Story\Plugin\TimeAndTravelDeclaration;

use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelDeclaration;
use App\Tests\Factory\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelDeclarationFactory;
use App\Tests\Story\_InitStory;
use Zenstruck\Foundry\Story;

/**
 * @method static TimeAndTravelDeclaration basic()
 * @method static TimeAndTravelDeclaration round_trip()
 * @method static TimeAndTravelDeclaration long_distance()
 */
final class TimeAndTravelDeclarationStory extends Story {

  public function build(): void {
    $this->addState('basic', TimeAndTravelDeclarationFactory::createOne([
      'member' => _InitStory::MEMBER_member_club_1(),
      'departureLocation' => 'Paris',
      'arrivalLocation' => 'Lyon',
      'kilometers' => 500,
      'hours' => '5.50',
      'description' => 'Déplacement compétition',
      'isRoundtrip' => true,
      'memberVehicle' => MemberVehicleStory::basic(),
    ]), 'declarations');

    $this->addState('round_trip', TimeAndTravelDeclarationFactory::createOne([
      'member' => _InitStory::MEMBER_member_club_1(),
      'departureLocation' => 'Marseille',
      'arrivalLocation' => 'Nice',
      'kilometers' => 300,
      'hours' => '3.00',
      'description' => 'Formation régionale',
      'isRoundtrip' => true,
      'memberVehicle' => MemberVehicleStory::electric(),
    ]), 'declarations');

    $this->addState('long_distance', TimeAndTravelDeclarationFactory::createOne([
      'member' => _InitStory::MEMBER_member_club_1(),
      'departureLocation' => 'Lille',
      'arrivalLocation' => 'Toulouse',
      'kilometers' => 1200,
      'hours' => '12.00',
      'description' => 'Finale nationale',
      'isRoundtrip' => true,
      'memberVehicle' => MemberVehicleStory::diesel(),
    ]), 'declarations');
  }
}
