<?php

namespace App\Entity\Interface;

use Symfony\Component\Uid\Uuid;

interface UuidEntityInterface {

  public function getId(): ?int;
  public function getUuid(): ?Uuid;
  public function setUuid(Uuid $uuid): static;
}
