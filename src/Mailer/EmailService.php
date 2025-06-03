<?php

namespace App\Mailer;

use App\Entity\Club;
use App\Entity\ClubDependent\Plugin\Emailing\Email;
use App\Entity\File;
use App\Enum\FileCategory;
use App\Enum\GlobalSetting;
use App\Service\FileService;
use App\Service\GlobalSettingService;
use App\Service\ImageService;
use App\Service\UuidService;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Part\DataPart;
use Twig\Environment;
use Symfony\Component\Mime\Part\File as MimePartFile;


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

  public function getClubEmail(Club $club, Email $email): ?TemplatedEmail {
    if (!$this->canSendEmail()) {
      return null;
    }

    $smtpEmail = new TemplatedEmail();

    $context['frontend_url'] = $this->params->get('app.frontend_url');
    $context['club'] = $club;
    $context['subject'] = $email->getTitle();
    $context['content'] = $email->getContent();
    $context['isNewsletter'] = $email->getIsNewsletter();
    if ($email->getIsNewsletter()) {
      $context['unsubscribe_url'] = $context['frontend_url'] . "/unsubscribe?club=" . UuidService::encodeToReadable($club->getUuid());
      $smtpEmail->getHeaders()->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
      $smtpEmail->getHeaders()->addTextHeader('List-Unsubscribe', '<'. $context['unsubscribe_url'] . '>');
    }

    // We set email logo
    $logo = $this->imageService->getClubLogoFile($club);

    // Logo fallback to Narvik one
    if (!$logo) {
      $logo = $this->imageService->getLogoFile();
    }

    if ($logo) {
      $logoPart = new DataPart($logo, 'logo.png');
      $context['logo'] = $logoPart->getFilename();
      $smtpEmail->addPart($logoPart->asInline());
    }

    // We set the sender
    $smtpSender = $this->globalSettingService->getSettingValue(GlobalSetting::SMTP_NEWSLETTER_SENDER);
    $smtpSenderName = "{$club->getName()} via Narvik";

    // We render the html
    $htmlBody = $this->twig->render('email/club-emailing/default.html.twig', $context);

    // We set the email
    $smtpEmail
      ->from(new Address($smtpSender, $smtpSenderName))
      ->replyTo($email->getReplyTo())
      ->subject($email->getTitle())
      ->html($htmlBody)
      ->context($context);

    return $smtpEmail;
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

  public function joinFile(TemplatedEmail $email, File $file, ?string $filename = null, bool $inline = false): void {
    $mimeFile =  $this->fileService->getMimePartFile($file);
    if (!$mimeFile) {
      return;
    }

    $this->joinMimePartFile($email, $mimeFile, $filename, $inline);
  }

  public function joinUploadedFile(TemplatedEmail $email, UploadedFile $uploadedFile, bool $inline = false, ?Club $club = null): void {
    $file = $this->fileService->importFile($uploadedFile, $uploadedFile->getClientOriginalName(), FileCategory::club_email, true, $club);
    $this->joinFile($email, $file, $file->getFilename(), $inline);
  }

  private function joinMimePartFile(TemplatedEmail $email, MimePartFile $mimeFile, ?string $filename = null, bool $inline = false): void {
    $attachmentName = $filename ?? $mimeFile->getFilename();

    $attachment = (new DataPart($mimeFile, $attachmentName));

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
