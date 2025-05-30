<?php

namespace App\Mailer;

use App\Entity\Club;
use App\Entity\File;
use App\Enum\GlobalSetting;
use App\Service\FileService;
use App\Service\GlobalSettingService;
use App\Service\ImageService;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Part\DataPart;
use Twig\Environment;

class EmailService {
  public function __construct(
    private readonly GlobalSettingService $globalSettingService,
    private readonly MessageBusInterface $bus,
    private readonly ImageService $imageService,
    private readonly Environment $twig,
    private readonly ParameterBagInterface $params,
    private readonly FileService $fileService,
  ) {
  }

  public function canSendEmail(): bool {
    $smtpSetting = $this->globalSettingService->getSettingValue(GlobalSetting::SMTP_ON);
    $smtpSender = $this->globalSettingService->getSettingValue(GlobalSetting::SMTP_SENDER);

    if (empty($smtpSender)) {
      return false;
    }

    if ($smtpSetting) {
      return $this->toBoolean($smtpSetting);
    }
    return false;
  }

  public function getEmail(string $template, string $subject, array $context = []): ?TemplatedEmail {
    if (!$this->canSendEmail()) {
      return null;
    }

    $email = new TemplatedEmail();

    $context['subject'] = $subject;
    $context['frontend_url'] = $this->params->get('app.frontend_url');

    $logo = $this->imageService->getLogoFile();
    $context['logo'] = '';
    if ($logo) {
      $logoPart = (new DataPart($logo, 'logo.png'));
      $context['logo'] = $logoPart->getFilename();
      $email->addPart($logoPart->asInline());
    }

    // We render the html
    $htmlBody = $this->twig->render('email/' . $template, $context);

    // We load the default sender configuration
    $smtpSender = $this->globalSettingService->getSettingValue(GlobalSetting::SMTP_SENDER);
    $smtpSenderName = $this->globalSettingService->getSettingValue(GlobalSetting::SMTP_SENDER_NAME) ?? 'Narvik';

    $email
      ->from(new Address($smtpSender, $smtpSenderName))
      ->subject($subject)
      ->html($htmlBody)
      ->context($context);

    return $email;
  }

  public function getClubEmail(Club $club, string $subject, string $content): ?TemplatedEmail {

    if (!$this->canSendEmail()) {
      return null;
    }

    $email = new TemplatedEmail();

    $context['subject'] = $subject;
    $context['frontend_url'] = $this->params->get('app.frontend_url');
    $context['content'] = $content;

    // We set email logo
    $logo = $this->imageService->getClubLogoFile($club);

    // Logo fallback to Narvik one
    if (!$logo) {
      $logo = $this->imageService->getLogoFile();
    }

    if ($logo) {
      $logoPart = (new DataPart($logo, 'logo.png'));
      $context['logo'] = $logoPart->getFilename();
      $email->addPart($logoPart->asInline());
    }

    // We set the sender
    $smtpSender = $this->globalSettingService->getSettingValue(GlobalSetting::SMTP_SENDER); // TODO: Add new var SMTP_NEWSLETTER_SENDER
    $smtpSenderName = "{$club->getName()} via Narvik";

    // We render the html
    $htmlBody = $this->twig->render('email/club-emailing/default.html.twig', $context);

    // We set the email
    $email
      ->from(new Address($smtpSender, $smtpSenderName))
      ->replyTo(null ?? $club->getContactEmail()) // TODO: Get the replyTo from clubSetting
      ->subject($subject)
      ->html($htmlBody)
      ->context($context);

    return $email;
  }

  public function sendEmail(?TemplatedEmail $email, ?string $to = null): bool {
    if (!$this->canSendEmail() || !$email) {
      return false;
    }

    if (!empty($to)) {
      $email->to($to);
    }

    // We load the smtp configuration
    $transport = $this->getMailerTransport();

    $mailer = new Mailer($transport, $this->bus);
    $mailer->send($email);

    return true;
  }

  public function joinFile(TemplatedEmail $email, File $file, string $filename, bool $inline = false): void {
    $mimeFile =  $this->fileService->getMimePartFile($file);
    if (!$mimeFile) {
      return;
    }

    $attachment = (new DataPart($mimeFile, $filename));

    if ($inline) {
      $email->addPart($attachment->asInline());
    } else {
      $email->addPart($attachment);
    }
  }

  public function getMailerTransport(): TransportInterface {
    $smtpHost = $this->globalSettingService->getSettingValue(GlobalSetting::SMTP_HOST);
    $smtpPort = $this->globalSettingService->getSettingValue(GlobalSetting::SMTP_PORT) ?? '25';
    $smtpUsername = $this->globalSettingService->getSettingValue(GlobalSetting::SMTP_USERNAME);
    $smtpPassword = $this->globalSettingService->getSettingValue(GlobalSetting::SMTP_PASSWORD);

    $dsn = '';
    if (!empty($smtpUsername)) {
      $dsn = urlencode($smtpUsername) . ':' . urlencode($smtpPassword) . '@';
    }

    $dsn .= $smtpHost . ':' . $smtpPort;

    return Transport::fromDsn('smtp://' . $dsn);
  }

  /**
   * Convert the passed value to boolean.
   * Will be `false` if value is incorrect
   *
   * @param $value
   * @return bool
   */
  private function toBoolean($value): bool {
    return is_bool($value) ? $value : !in_array(strtolower((string) $value), ['', '0', 'false']);
  }
}
