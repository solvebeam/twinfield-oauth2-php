<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\OAuth2\Provider;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Token\AccessTokenInterface;
use League\OAuth2\Client\Tool\BearerAuthorizationTrait;
use Psr\Http\Message\ResponseInterface;
use SolveBeam\Twinfield\OAuth2\AccessTokenValidation;
use SolveBeam\Twinfield\OAuth2\Scope;
use SolveBeam\Twinfield\OAuth2\UnexpectedResponseException;

/**
 * What every provider that issues Twinfield access tokens has in common: the user info, the access
 * token validation, the scopes and the errors. Only where the user is sent to log in and where
 * tokens are requested differs per provider.
 *
 * `Twinfield` is the provider for your own Twinfield client. Extend this class for a service that
 * issues Twinfield access tokens in another way, such as an OAuth proxy.
 */
abstract class AbstractTwinfieldProvider extends AbstractProvider
{
    use BearerAuthorizationTrait;

    private const string RESOURCE_OWNER_URL = 'https://login.twinfield.com/auth/authentication/connect/userinfo';
    private const string ACCESS_TOKEN_VALIDATION_URL =
        'https://login.twinfield.com/auth/authentication/connect/accesstokenvalidation';

    public function getResourceOwnerDetailsUrl(AccessToken $token): string
    {
        return self::RESOURCE_OWNER_URL;
    }

    /**
     * Asks Twinfield who the access token belongs to and which cluster API calls go to.
     *
     * @throws IdentityProviderException When Twinfield rejects the token.
     * @throws UnexpectedResponseException When a claim is missing, e.g. because a scope was not requested.
     */
    public function getAccessTokenValidation(AccessTokenInterface $token): AccessTokenValidation
    {
        $tokenValue = trim($token->getToken());

        if ($tokenValue === '') {
            throw new IdentityProviderException('Access token is missing', 0, ['error' => 'token_missing']);
        }

        $url = self::ACCESS_TOKEN_VALIDATION_URL . '?token=' . rawurlencode($tokenValue);
        $request = $this->getAuthenticatedRequest(self::METHOD_GET, $url, $token);
        $response = $this->getResponse($request);
        /**
         * League documents parseResponse() as returning an array, but it returns the body as a
         * string when it is not JSON.
         *
         * @var array<mixed>|string $data
         */
        $data = $this->parseResponse($response);

        $this->checkResponse($response, $data);

        if (!is_array($data)) {
            throw new UnexpectedResponseException('Access token validation did not return a JSON object.');
        }

        return AccessTokenValidation::fromArray($data);
    }

    /**
     * The identity scopes: Twinfield does not log in without them.
     *
     * @return list<string>
     */
    protected function getDefaultScopes(): array
    {
        return Scope::values(...Scope::identity());
    }

    /**
     * Drops `approval_prompt`, a Google-specific parameter League adds by default.
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    protected function getAuthorizationParameters(array $options): array
    {
        $parameters = parent::getAuthorizationParameters($options);

        unset($parameters['approval_prompt']);

        return $parameters;
    }

    /**
     * OAuth 2.0 scopes are space-delimited (RFC 6749, section 3.3).
     */
    protected function getScopeSeparator(): string
    {
        return ' ';
    }

    /**
     * @param array<mixed>|string $data Parsed response data, or the body when it is not JSON.
     */
    protected function checkResponse(ResponseInterface $response, $data): void
    {
        $statusCode = $response->getStatusCode();

        if ($statusCode >= 400) {
            $message = $data['error_description'] ?? $data['message'] ?? $response->getReasonPhrase();
            $error = $data['error'] ?? (string) $statusCode;

            throw new IdentityProviderException($message, $statusCode, ['error' => $error]);
        }
    }

    /**
     * @param array<string, mixed> $response
     */
    protected function createResourceOwner(array $response, AccessToken $token): TwinfieldResourceOwner
    {
        return new TwinfieldResourceOwner($response);
    }
}
