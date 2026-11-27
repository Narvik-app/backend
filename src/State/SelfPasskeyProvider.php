<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\User;
use App\Entity\WebauthnCredential;
use App\Repository\WebauthnCredentialRepository;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<WebauthnCredential>
 */
final readonly class SelfPasskeyProvider implements ProviderInterface {
  public function __construct(
    private Security $security,
    private WebauthnCredentialRepository $repository,
  ) {
  }

  /**
   * @return WebauthnCredential[]
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): array {
    $user = $this->security->getUser();
    if (!$user instanceof User) {
      return [];
    }

    return $this->repository->findByUser($user);
  }
}
