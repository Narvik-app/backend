<?php

namespace App\Dto;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body of POST /self/passkeys: the browser attestation response for a registration challenge.
 * Same denormalization group as the resource.
 */
final class PasskeyRegistrationInput {
  /** Returned by POST /self/passkeys/-/options */
  #[Groups(['passkey-write'])]
  #[Assert\NotBlank]
  public string $challengeId = '';

  /** The JSON returned by navigator.credentials.create() */
  #[ApiProperty(schema: ['type' => 'object'])]
  #[Groups(['passkey-write'])]
  #[Assert\NotBlank]
  public array $credential = [];

  /** Optional, a default one is given */
  #[Groups(['passkey-write'])]
  #[Assert\Length(max: 100)]
  public ?string $name = null;
}
