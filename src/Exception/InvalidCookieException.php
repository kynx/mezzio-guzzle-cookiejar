<?php

/**
 * @license  : MIT
 *
 * @copyright: 2019 Matt Kynaston <matt@kynx.org>
 */

declare(strict_types=1);

namespace Kynx\Guzzle\Mezzio\Exception;

use RuntimeException;

final class InvalidCookieException extends RuntimeException implements ExceptionInterface
{
}
