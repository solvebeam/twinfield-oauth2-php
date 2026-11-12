<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\OAuth2\Provider;

/**
 * Logs in at Twinfield with your own Twinfield client ID and secret.
 *
 *   $provider = new Twinfield(['clientId' => …, 'clientSecret' => …, 'redirectUri' => …]);
 *
 * @see https://developers.twinfield.com/documentation/api/authentication/openid-connect-auth
 */
final class Twinfield extends AbstractTwinfieldProvider
{
    private const string AUTHORIZATION_URL = 'https://login.twinfield.com/auth/authentication/connect/authorize';
    private const string ACCESS_TOKEN_URL = 'https://login.twinfield.com/auth/authentication/connect/token';

    public function getBaseAuthorizationUrl(): string
    {
        return self::AUTHORIZATION_URL;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function getBaseAccessTokenUrl(array $params): string
    {
        return self::ACCESS_TOKEN_URL;
    }
}
