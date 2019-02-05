<?php
/**
 * @copyright: 2019 Matt Kynaston <matt@kynx.org>
 * @license  : MIT
 */
declare(strict_types=1);

namespace Kynx\Guzzle\Expressive\Exception;

use RuntimeException;

final class InvalidCookieException extends RuntimeException implements ExceptionInterface
{
}
