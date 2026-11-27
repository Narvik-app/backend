<?php

namespace App\Controller;

use App\Controller\Abstract\AbstractController;
use App\Entity\User;
use App\Enum\UserRole;
use App\Service\WebauthnService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SelfPasskeyRegisterOptions extends AbstractController {

  public function __invoke(WebauthnService $webauthnService): JsonResponse {
    $user = $this->getUser();
    if (!$user instanceof User || $user->getRole() === UserRole::badger) {
      throw new HttpException(Response::HTTP_BAD_REQUEST);
    }

    return new JsonResponse($webauthnService->createRegistrationOptions($user));
  }
}
