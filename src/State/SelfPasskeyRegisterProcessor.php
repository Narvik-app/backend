<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\PasskeyRegistrationInput;
use App\Entity\User;
use App\Entity\WebauthnCredential;
use App\Enum\UserRole;
use App\Service\WebauthnService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Finish a passkey registration for the logged user.
 *
 * @implements ProcessorInterface<PasskeyRegistrationInput, WebauthnCredential>
 */
final readonly class SelfPasskeyRegisterProcessor implements ProcessorInterface {
  public function __construct(
    private Security $security,
    private WebauthnService $webauthnService,
  ) {
  }

  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): WebauthnCredential {
    $user = $this->security->getUser();
    if (!$user instanceof User || $user->getRole() === UserRole::badger) {
      throw new HttpException(Response::HTTP_BAD_REQUEST);
    }

    return $this->webauthnService->verifyRegistration($user, $data->challengeId, $data->credential, $data->name);
  }
}
