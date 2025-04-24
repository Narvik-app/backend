<?php

namespace App\Controller;

use App\Controller\Abstract\AbstractController;
use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\UserRepository;
use App\Service\TurnstileService;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class UserInitiateRegister extends AbstractController {

  public function __invoke(Request $request, UserRepository $userRepository, UserService $userService, EntityManagerInterface $em, TurnstileService $turnstileService): JsonResponse {
    $payloadRequiredFields = ['email', 'accountType'];

    if ($turnstileService->isEnabled()) {
      $payloadRequiredFields[] = 'token';
    }

    $payload = $this->checkAndGetJsonValues($request, $payloadRequiredFields);
    $email = $payload['email'];

    // We must check the token is valid
    if ($turnstileService->isEnabled()) {
      $token = $payload['token'];
      $validated = $turnstileService->verifyToken($token);
      if (!$validated) {
        throw new HttpException(Response::HTTP_BAD_REQUEST, 'Invalid cf token.');
      }
    }

    $user = $userRepository->findOneByEmail($email);
    if ($user) {
      if ($payload['accountType'] !== 'club' && $user->isAccountActivated()) {
        throw new HttpException(Response::HTTP_BAD_REQUEST, 'User already registered.');
      }
    } else {
      $user = new User();
      $user
        ->setRole(UserRole::user)
        ->setEmail($email);
      $em->persist($user);
      $em->flush();
    }

    // We force the account validation for club creation (in that case user can already have an account)
    $initialised = $userService->initiateAccountValidation($user, $payload['accountType'] === 'club');
    if (!$initialised) {
      throw new HttpException(Response::HTTP_BAD_REQUEST);
    }

    return new JsonResponse();
  }

}
