<?php

namespace App\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Club;
use App\Entity\Config;
use App\Entity\User;
use App\Enum\ClubRole;
use App\Enum\UserRole;
use App\Mailer\EmailService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class ConfigProvider implements ProviderInterface {

  public function __construct(
    private readonly Security $security,
    private readonly AuthorizationCheckerInterface $authorizationChecker,
    private readonly ContainerBagInterface $params,
    private readonly EmailService $emailService,
  ) {
  }

  public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null {
    if ($operation instanceof CollectionOperationInterface) {
      return null;
    }

    $config = new Config();
    $this->getDefaultConfig($config);

    $user = $this->security->getUser();

    if (!$user instanceof User) {
      return $config;
    }

    $this->getUserConfig($config, $user);

    return $config;
  }

  public function getDefaultConfig(Config $config): void {
    if ($this->authorizationChecker->isGranted(UserRole::super_admin->value) ||
        $this->params->get('app.expose_version')
    ) {
      $config->setAppVersion(\Composer\InstalledVersions::getRootPackage()['pretty_version']);
    }

    $config->setLogo("/images/logo-narvik.png");
    $config->setLogoWhite("/images/logo-narvik-white.png");

    // Email notifications
    $config->addModule('notifications', [
      'enabled' => $this->emailService->canSendEmail(),
    ]);
  }

  public function getUserConfig(Config $config, User $user): void {
    $config->setId('user');

    foreach ($user->getLinkedProfiles() as $profile) {
      $id = $profile->getId();
      /** @var Club $club */
      $club = $profile->getClub();

      // User is badger or supervisor
      if ($this->authorizationChecker->isGranted(ClubRole::supervisor->value, $club) ||
          $this->authorizationChecker->isGranted(ClubRole::badger->value, $club)) {
        $config->addProfileModule($id, 'presences', [
          'enabled' => $club->getPresencesEnabled(),
        ]);
      }

      // User a supervisor, he can have access to the sale management
      if ($this->authorizationChecker->isGranted(ClubRole::supervisor->value, $club)) {
        $config->addProfileModule($id, 'sales', [
          'enabled' => $club->getSalesEnabled(),
        ]);
      }
    }
  }

}
