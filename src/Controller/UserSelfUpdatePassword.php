<?php

namespace App\Controller;

use App\Controller\Abstract\AbstractController;
use App\Entity\User;
use App\Enum\UserRole;
use App\Service\UserService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserSelfUpdatePassword extends AbstractController {

  public function __invoke(Request $request, UserPasswordHasherInterface $passwordHasher, UserService $userService): JsonResponse {
    $user = $this->getUser();
    if (!$user instanceof User || $user->getRole() === UserRole::badger) {
      throw new HttpException(Response::HTTP_BAD_REQUEST);
    }

    $payload = $this->checkAndGetJsonValues($request, ['current', 'new']);

    $currentPwd = $payload['current'];
    $newPwd = mb_trim((string) $payload['new']);

    if (!$passwordHasher->isPasswordValid($user, $currentPwd)) {
      throw new HttpException(Response::HTTP_BAD_REQUEST, "Invalid password");
    }

    $changeError = $userService->changeUserPassword($user, $newPwd);
    if ($changeError) {
      throw new HttpException(Response::HTTP_BAD_REQUEST, $changeError);
    }

    return new JsonResponse();
  }

}
