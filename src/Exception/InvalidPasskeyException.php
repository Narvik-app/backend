<?php

namespace App\Exception;

/**
 * A passkey ceremony failed (unknown or expired challenge, invalid or unknown passkey...).
 * Returned as a 400 by the passkey operations, as invalid credentials by the passkey grant.
 */
class InvalidPasskeyException extends \RuntimeException {
}
