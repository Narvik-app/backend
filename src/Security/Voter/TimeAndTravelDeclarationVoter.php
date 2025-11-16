<?php

namespace App\Security\Voter;

use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelDeclaration;
use App\Entity\User;
use App\Entity\UserMember;
use App\Enum\ClubRole;
use App\Service\RequestService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

class TimeAndTravelDeclarationVoter extends Voter {
  public const CREATE = 'TIME_TRAVEL_DECLARATION_CREATE';
  public const READ = 'TIME_TRAVEL_DECLARATION_READ';
  public const UPDATE = 'TIME_TRAVEL_DECLARATION_UPDATE';
  public const DELETE = 'TIME_TRAVEL_DECLARATION_DELETE';

  public function __construct(
    private readonly Security $security,
    private readonly RequestStack $requestStack,
    private readonly RequestService $requestService,
  ) {
  }

  protected function supports(string $attribute, mixed $subject): bool {
    return in_array($attribute, [self::CREATE, self::READ, self::UPDATE, self::DELETE])
      && $subject instanceof TimeAndTravelDeclaration;
  }

  protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool {
    $user = $token->getUser();

    // If the user is not logged in, deny access
    if (!$user instanceof User || !$subject instanceof TimeAndTravelDeclaration) {
      return false;
    }

    $selectedProfile = $this->requestService->getSelectedProfileFromRequest($this->requestStack->getCurrentRequest());


    dump($attribute, $subject, $selectedProfile);

    // Get the current user member
    // TODO: Implement the member logic
//    $userMember = $this->getUserMember($token);
//    if (!$userMember) {
//      return false;
//    }
//
//    // Allow access if the user is the owner of the declaration
//    if ($declaration->getMember() === $userMember->getMember()) {
//      return true;
//    }

    // ClubRole::admin can do anything
    if ($this->security->isGranted(ClubRole::admin->value, $subject)) {
      // return true;
    }

    return false;
  }
}
