<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\WebauthnCredential;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WebauthnCredential>
 */
class WebauthnCredentialRepository extends ServiceEntityRepository {
  public function __construct(ManagerRegistry $registry) {
    parent::__construct($registry, WebauthnCredential::class);
  }

  public function findOneByCredentialId(string $credentialId): ?WebauthnCredential {
    return $this->findOneBy(['credentialId' => $credentialId]);
  }

  /**
   * @return WebauthnCredential[]
   */
  public function findByUser(User $user): array {
    return $this->findBy(['user' => $user], ['createdAt' => 'ASC', 'id' => 'ASC']);
  }
}
