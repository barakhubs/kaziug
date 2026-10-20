<?php

declare(strict_types=1);

namespace App\Domain;

use RuntimeException;

/** A requested record does not exist. Rendered as a 404 by the error handler. */
final class NotFoundException extends RuntimeException
{
}
