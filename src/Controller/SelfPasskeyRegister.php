<?php

namespace App\Controller;

use App\Controller\Abstract\AbstractController;
use App\Entity\User;
use App\Entity\WebauthnCredential;
use App\Enum\UserRole;
use App\Service\WebauthnService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SelfPasskeyRegister extends AbstractController {

  public function __invoke(Request $request, WebauthnService $webauthnService): WebauthnCredential {
    $user = $this->getUser();
    if (!$user instanceof User || $user->getRole() === UserRole::badger) {
      throw new HttpException(Response::HTTP_BAD_REQUEST);
    }

    $payload = $this->checkAndGetJsonValues($request, ['challengeId', 'credential']);
    if (!is_string($payload['challengeId']) || !is_array($payload['credential'])) {
      throw new HttpException(Response::HTTP_BAD_REQUEST, 'Invalid passkey response.');
    }

    $name = $payload['name'] ?? null;
    return $webauthnService->verifyRegistration(
      $user,
      $payload['challengeId'],
      $payload['credential'],
      is_string($name) ? $name : null,
    );
  }
}
