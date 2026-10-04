<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/** Secret PPPoE tidak ada di router; mengulang tidak akan membantu. */
final class SecretNotFoundException extends RuntimeException {}
