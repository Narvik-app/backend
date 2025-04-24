<?php

namespace App\Controller;

use App\Controller\Abstract\AbstractController;
use App\Entity\Club;
use App\Entity\User;
use App\Enum\UserSecurityCodeTrigger;
use App\Repository\ClubRepository;
use App\Repository\UserRepository;
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
      $this->userService->initiateAccountValidation($user); // We trigger a new password query
      throw new HttpException(Response::HTTP_BAD_REQUEST, "A new security code has been sent.");
    }

    // We activate the account and check all fields match.
    // The account can be already activated in the case of a club creation (if the user email was already used by another club)
    if (!$user->isAccountActivated()) {
      $this->userService->activateAccount($user, $firstname, $lastname, $password);
    }

    // We can now consume the security code
    $this->userService->consumeAllSecurityCodes($user, UserSecurityCodeTrigger::accountValidation);

    if ($accountType === 'club') {
      $this->createClub($request, $user);
    }

    return new JsonResponse();
  }

  private function createClub(Request $request, User $user): void {
    $payload = $this->checkAndGetJsonValues($request, ['clubName', 'clubSiret', 'clubVat', 'clubEmail', 'clubPhone', 'clubAddress', 'clubZipCode', 'clubCity']);
  }

}
