<?php
/**
 * @copyright: 2019 Matt Kynaston <matt@kynx.org>
 * @license  : MIT
 */
declare(strict_types=1);

namespace Kynx\Guzzle\Expressive\Exception;

use RuntimeException;

/**
 * Exception thrown when expressive session not found
 */
final class NoSessionException extends RuntimeException implements ExceptionInterface
{
}
