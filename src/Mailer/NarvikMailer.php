<?php

namespace App\Mailer;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;

#[AutoconfigureTag('mailer.transport_factory')]
final readonly class NarvikMailer implements TransportFactoryInterface {
  public function __construct(
    private EmailService $emailService
  ) {
  }

  public function supports(Dsn $dsn): bool {
    return $dsn->getScheme() === 'narvik';
  }

  public function create(Dsn $dsn): TransportInterface {
    return $this->emailService->getMailerTransport();
  }


}
