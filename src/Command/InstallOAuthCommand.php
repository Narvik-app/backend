<?php

namespace App\Command;

use App\Enum\UserRole;
use App\Repository\ClubDependent\MemberRepository;
use App\Service\GlobalSettingService;
use Doctrine\ORM\EntityManagerInterface;
use League\Bundle\OAuth2ServerBundle\Manager\ClientManagerInterface;
use League\Bundle\OAuth2ServerBundle\Model\ClientInterface as OAuth2ClientInterface;
use League\Bundle\OAuth2ServerBundle\ValueObject\Grant;
use League\Bundle\OAuth2ServerBundle\ValueObject\Scope;
use phpDocumentor\Reflection\PseudoTypes\IntegerRange;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsCommand(name: 'install:oauth', description: 'Install oauth and his minimum require client')]
class InstallOAuthCommand extends Command {
  /**
   * The badger client is a kiosk/device client (see App\Controller\SecurityController::loginBadger).
   * It never authenticates with a client secret (/auth/bdg checks the club's badger token instead),
   * so it must be a public client restricted to the refresh_token grant.
   */
  private const array BADGER_GRANTS = ['refresh_token'];
  private const array BADGER_SCOPES = ['badger'];

  /**
   * The frontend only ever uses the password grant (initial login) and refresh_token
   * (session renewal) - see narvik-front's app/composables/api/api.ts. Other grants
   * (client_credentials, authorization_code, implicit) are pointless to allow here.
   */
  private const array FRONT_GRANTS = ['password', 'refresh_token'];
  private const array FRONT_SCOPES = ['all'];

  private SymfonyStyle $io;

  public function __construct(
    private readonly ParameterBagInterface $params,
    private readonly ClientManagerInterface $clientManager,
    private readonly KernelInterface $kernel,
    private readonly Filesystem $fs,
  ) {
    parent::__construct();
  }

  protected function configure(): void {
    $this->addOption('force', 'f',InputOption::VALUE_NONE, 'Force regenerating the key');
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $this->io = new SymfonyStyle($input, $output);

    $this->generateKeys($input->getOption('force'));
    $this->generateClients();

    $this->io->success('OAuth configuré');
    return Command::SUCCESS;
  }

  private function generateKeys(bool $force = false): void {
    $this->io->section("Génération des clés JWT");

    $path = $this->kernel->getProjectDir() . "/config/jwt";
    $existingJwt = $this->fs->exists(["$path/private.pem", "$path/public.pem"]);

    $oauthPassphrase = $this->params->get('app.oauth_passphrase');
    if (!$oauthPassphrase) {
      throw new \Exception("Env var 'OAUTH_PASSPHRASE' is not defined");
    }

    if ($existingJwt) {
      $this->io->info("Clés JWT présentes");

      if ($force) {
        $question = new Question("Voulez-vous générer des nouvelles clés JWT ? (oui/non)", "n");
        $question->setValidator(function (?string $value): string {
          if (empty($value)) {
            $value = "o";
          }
          return strtolower($value);
        });
        $question = $this->io->askQuestion($question);
        if ($question[0] !== "o") {
          return;
        }
      } else {
        return;
      }
    }

    $jwtKeyGenerateInput = new ArrayInput([
      'command' => 'league:oauth2-server:generate-keypair',
      '--overwrite' => true
    ]);
    $this->getApplication()->doRun($jwtKeyGenerateInput, $this->io);
  }


  private function generateClients(): void {
    $this->generateBadgerClient();
    $this->generateFrontClient();
  }

  private function generateBadgerClient(): void {
    $this->io->section("Création du client badger");

    $client = $this->clientManager->find('badger');
    if ($client) {
      $this->reconcileBadgerClient($client);
      return;
    }

    $command = new ArrayInput([
      'command' => 'league:oauth2-server:create-client',
      'name' => 'badger',
      'identifier' => 'badger',
      '--public' => true,
      '--grant-type' => self::BADGER_GRANTS,
      '--scope' => self::BADGER_SCOPES,
    ]);
    $this->getApplication()->doRun($command, $this->io);
  }

  /**
   * Unlike badger, this client has no known-bad legacy shape to repair, so an existing
   * client is simply left untouched - there's no way to recover its already-hashed secret
   * to display it again, and no need to guess whether an operator deliberately customised
   * its grants/scopes since.
   */
  private function generateFrontClient(): void {
    $this->io->section("Création du client frontend");

    if ($this->clientManager->find('front')) {
      $this->io->info("Client déjà enregistré");
      return;
    }

    $command = new ArrayInput([
      'command' => 'league:oauth2-server:create-client',
      'name' => 'Narvik front',
      'identifier' => 'front',
      '--grant-type' => self::FRONT_GRANTS,
      '--scope' => self::FRONT_SCOPES,
    ]);
    $this->getApplication()->doRun($command, $this->io);
    $this->io->warning("Le secret ci-dessus ne sera plus jamais affiché : reportez-le dans NUXT_OAUTH_CLIENT_ID / NUXT_OAUTH_CLIENT_SECRET côté frontend avant de continuer.");
  }

  /**
   * Repairs an already-provisioned badger client.
   * Demotes it to public and restricts it to the refresh_token grant + badger scope.
   * Safe to re-run: a no-op once the client is already correct.
   */
  private function reconcileBadgerClient(OAuth2ClientInterface $client): void {
    $this->io->info("Client déjà enregistré");

    $changes = [];

    if ($client->isConfidential()) {
      $client->setSecret('');
      $changes[] = 'secret retiré (client rendu public)';
    }

    $currentGrants = array_map(strval(...), $client->getGrants());
    if ($currentGrants !== self::BADGER_GRANTS) {
      $client->setGrants(...array_map(fn (string $grant) => new Grant($grant), self::BADGER_GRANTS));
      $changes[] = 'grants restreints à ' . implode(', ', self::BADGER_GRANTS);
    }

    $currentScopes = array_map(strval(...), $client->getScopes());
    if ($currentScopes !== self::BADGER_SCOPES) {
      $client->setScopes(...array_map(fn (string $scope) => new Scope($scope), self::BADGER_SCOPES));
      $changes[] = 'scopes réglés sur ' . implode(', ', self::BADGER_SCOPES);
    }

    if (empty($changes)) {
      $this->io->info("Client badger déjà conforme, aucune modification");
      return;
    }

    $this->clientManager->save($client);
    $this->io->success("Client badger corrigé : " . implode(' ; ', $changes));
    if (!$client->isActive()) {
      $this->io->warning("Le client badger est inactif, mais étant public, la désactivation ne bloque pas le renouvellement de jeton (isActive() n'est vérifié que pour les clients confidentiels). Pour révoquer l'accès badger, supprimez le client ou révoquez ses jetons.");
    }
  }
}
