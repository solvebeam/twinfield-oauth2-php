<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\OAuth2;

use RuntimeException;

/**
 * Twinfield answered with something this library does not expect, e.g. an access token validation
 * without the claims of a scope that was not requested.
 */
final class UnexpectedResponseException extends RuntimeException
{
}
