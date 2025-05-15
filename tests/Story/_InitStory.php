<?php

namespace App\Tests\Story;

use App\Entity\Club;
use App\Entity\ClubDependent\Member;
use App\Entity\User;
use App\Enum\ClubRole;
use App\Tests\Factory\ClientFactory;
use App\Tests\Factory\ClubFactory;
use App\Tests\Factory\MemberFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\Factory\UserMemberFactory;
use Ramsey\Uuid\Uuid;
use Zenstruck\Foundry\Story;

/**
 * @method static User USER_super_admin()
 * @method static User USER_admin_club_1() This user is also a member of club 2 with just member role
 * @method static User USER_admin_club_2()
 * @method static User USER_supervisor_club_1()
 * @method static User USER_member_club_1()
 * @method static User USER_member_club_2()
 * @method static Club club_1()
 * @method static Club club_2()
 * @method static Member MEMBER_admin_club_1()
 * @method static Member MEMBER_admin_club_2()
 * @method static Member MEMBER_supervisor_club_1()
 * @method static Member MEMBER_member_club_1()
 * @method static Member MEMBER_member_club_2()
 */
final class _InitStory extends Story {
  public function build(): void {
    // We create the frontend client
    ClientFactory::createOne([
      'name'       => 'test',
      'identifier' => 'test',
    ]);
    ClientFactory::createOne([
      'name'       => 'badger',
      'identifier' => 'badger',
    ]);

    $this->addState('USER_super_admin', UserFactory::new()->superAdmin("admin@admin.com")->create(), 'super_admin');

    $this->addState('club_1', ClubFactory::createOne([
        'uuid' => Uuid::fromString('0193b683-b858-73ee-a9c7-41c84cd27fe2'),
        'name' => 'Club 1',
        'presencesEnabled' => true,
        'salesEnabled' => true,
        'badgerToken' => 'club1longbadgertoken',
      ]), 'clubs');
    $this->addState('club_2', ClubFactory::createOne([
      'uuid' => Uuid::fromString('0193b683-b85b-71fd-9741-40c4bbf4feaf'),
      'name' => 'Club 2',
      'salesEnabled' => false,
      'badgerToken' => 'club2longbadgertoken',
    ]), 'clubs');

    $this->addToPool('clubs', ClubFactory::createMany(3));

    // We create a one club admin and supervisor for each
    $this->addState('USER_admin_club_1', UserFactory::createOne([
      'email' => 'admin@club1.fr',
      'plainPassword' => 'admin123',
      'accountActivated' => true,
    ]), 'users');
    $this->addState('USER_admin_club_2', UserFactory::createOne([
      'email' => 'admin@club2.fr',
      'plainPassword' => 'admin123',
      'accountActivated' => true,
    ]), 'users');
    $this->addState('USER_supervisor_club_1', UserFactory::createOne([
      'email' => 'supervisor@club1.fr',
      'plainPassword' => 'admin123',
      'accountActivated' => true,
    ]), 'users');
    $this->addState('USER_member_club_1', UserFactory::createOne([
      'email' => 'member@club1.fr',
      'plainPassword' => 'member123',
      'accountActivated' => true,
    ]), 'users');
    $this->addState('USER_member_club_2', UserFactory::createOne([
      'email' => 'member@club2.fr',
      'plainPassword' => 'member123',
      'accountActivated' => true,
    ]), 'users');

    // We create the association club <-> Member <-> User
    $this->addState('MEMBER_admin_club_1', MemberFactory::createOne([
      'club' => self::club_1(),
      'email' => 'admin@club1.fr',
      'lastname' => 'Adminclub1'
    ]));
    $this->addState('MEMBER_admin_club_2', MemberFactory::createOne([
      'club' => self::club_2(),
      'email' => 'admin@club2.fr',
    ]));
    $this->addState('MEMBER_supervisor_club_1', MemberFactory::createOne([
      'club' => self::club_1(),
      'email' => 'supervisor@club1.fr',
    ]));
    $this->addState('MEMBER_member_club_1', MemberFactory::createOne([
      'club' => self::club_1(),
      'email' => 'member@club1.fr',
      'licence' => '10000001'
    ]));
    $this->addState('MEMBER_member_club_2', MemberFactory::createOne([
      'club' => self::club_2(),
      'email' => 'member@club2.fr',
    ]));

    UserMemberFactory::createOne([
      'member' => self::MEMBER_admin_club_1(),
      'user' => self::USER_admin_club_1(),
      'role' => ClubRole::admin,
    ]);
    UserMemberFactory::createOne([
      'member' => self::MEMBER_admin_club_2(),
      'user' => self::USER_admin_club_2(),
      'role' => ClubRole::admin,
    ]);
    UserMemberFactory::createOne([
      'member' => self::MEMBER_supervisor_club_1(),
      'user' => self::USER_supervisor_club_1(),
      'role' => ClubRole::supervisor,
    ]);
    UserMemberFactory::createOne([
      'member' => self::MEMBER_member_club_1(),
      'user' => self::USER_member_club_1(),
      'role' => ClubRole::member,
    ]);
    UserMemberFactory::createOne([
      'member' => self::MEMBER_member_club_2(),
      'user' => self::USER_member_club_2(),
      'role' => ClubRole::member,
    ]);

    // Some users linked with no club
     $users = UserFactory::createMany(5);

    // We create some member
//    $members = MemberFactory::createMany(10, [
//      'memberSeasons' => MemberSeasonFactory::new()->many(0, 4),
//    ]);
//
//    // We link the member with user
//    foreach ($members as $member) {
//      UserMemberFactory::createOne([
//        'user' => faker()->randomElement($users),
//        'member' => $member,
//      ]);
//    }
  }
}
