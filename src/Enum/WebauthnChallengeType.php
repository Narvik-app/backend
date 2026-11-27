<?php

namespace App\Enum;

enum WebauthnChallengeType: string {
  /** Passkey registration (navigator.credentials.create) */
  case creation = 'CREATION';

  /** Passkey login (navigator.credentials.get) */
  case request = 'REQUEST';
}
