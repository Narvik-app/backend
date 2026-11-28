<?php

namespace App\Exception;

/**
 * The authenticator already registered this passkey. Returned as a 409.
 */
class PasskeyAlreadyRegisteredException extends \RuntimeException {
}
