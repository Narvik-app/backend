<?php

namespace App\Controller;

use App\Controller\Abstract\AbstractController;
use App\Entity\Club;
use App\Entity\ClubDependent\Member;
use App\Entity\User;
use App\Enum\UserSecurityCodeTrigger;
use App\Repository\ClubRepository;
use App\Repository\UserRepository;
use App\Service\ClubService;
use App\Service\UserService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class UserRegister extends AbstractController {
  public function __construct(
    private readonly UserRepository $userRepository,
    private readonly UserService $userService,
    private readonly ClubRepository $clubRepository,
    private readonly ClubService $clubService,
  ) {
  }


  public function __invoke(Request $request): JsonResponse {
    $payload = $this->checkAndGetJsonValues($request, ['accountType', 'email', 'securityCode', 'firstname', 'lastname', 'password']);
    $accountType = $payload['accountType'] === 'club' ? 'club' : 'personal'; // We force the account type to be either club or personal

    $email = $payload['email'];
    $securityCode = $payload['securityCode'];
    $firstname = $payload['firstname'];
    $lastname = $payload['lastname'];
    $password = $payload['password'];

    $user = $this->userRepository->findOneByEmail($email);
    if (!$user) {
      throw new HttpException(Response::HTTP_BAD_REQUEST);
    }

    $validated = $this->userService->validateSecurityCode($user, UserSecurityCodeTrigger::accountValidation, $securityCode);
    if (!$validated) {
      $this->userService->initiateAccountValidation($user, true); // We trigger a new password query
      throw new HttpException(Response::HTTP_BAD_REQUEST, "A new security code has been sent.");
    }

    // We activate the account and check all fields match.
    // The account can be already activated in the case of a club creation (if the user email was already used by another club)
    if (!$user->isAccountActivated()) {
      $this->userService->activateAccount($user, $firstname, $lastname, $password);
    }

    if ($accountType === 'club') {
      $this->createClub($request, $user);
    }

    // We can now consume the security code
    $this->userService->consumeAllSecurityCodes($user, UserSecurityCodeTrigger::accountValidation);

    return new JsonResponse();
  }

  private function createClub(Request $request, User $user): void {
    $payload = $this->checkAndGetJsonValues($request, ['clubName', 'clubEmail', 'clubAddress', 'clubZipCode', 'clubCity']);

    $name = $payload['clubName'];
    $siret = $payload['clubSiret'] ?? null;
    $vat = $payload['clubVat'] ?? null;
    $email = $payload['clubEmail'];
    $phone = $payload['clubPhone'] ?? null;
    $address = $payload['clubAddress'];
    $zipCode = $payload['clubZipCode'];
    $city = $payload['clubCity'];

    if ($this->clubRepository->findOneByName($name)) {
      throw new HttpException(Response::HTTP_BAD_REQUEST, "Club with same name already exists.");
    }

    $club = new Club();
    $club
      ->setComment("Created from registration page")
      ->setName($name)
      ->setContactEmail($email)
      ->setContactPhone($phone)
      ->setAddress($address)
      ->setZipCode($zipCode)
      ->setCity($city)
      ->setSiret($siret)
      ->setVat($vat);

    // Activate trial
    $this->clubService->activateTrial($club);

    // We link the current user to that club
    $this->clubService->linkUserToClub($club, $user);
  }

}
