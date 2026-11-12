<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\OAuth2\Tests\Provider;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use SolveBeam\Twinfield\OAuth2\Provider\Twinfield;
use SolveBeam\Twinfield\OAuth2\Provider\TwinfieldResourceOwner;
use SolveBeam\Twinfield\OAuth2\Scope;
use SolveBeam\Twinfield\OAuth2\UnexpectedResponseException;

final class TwinfieldTest extends TestCase
{
    private MockHandler $mock;

    public function testBuildsTheAuthorizationUrl(): void
    {
        $provider = $this->provider();
        $url = $provider->getAuthorizationUrl(['scope' => Scope::values(...Scope::identity())]);

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        self::assertStringStartsWith('https://login.twinfield.com/auth/authentication/connect/authorize?', $url);
        self::assertSame('client', $query['client_id']);
        self::assertSame('code', $query['response_type']);
        self::assertSame('https://example.com/callback', $query['redirect_uri']);
        self::assertSame('openid twf.user twf.organisation twf.organisationUser offline_access', $query['scope']);
        self::assertSame($provider->getState(), $query['state']);
        self::assertArrayNotHasKey('approval_prompt', $query);
    }

    public function testRequestsAnAccessToken(): void
    {
        $provider = $this->provider(self::json([
            'access_token' => 'access-1',
            'refresh_token' => 'refresh-1',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ]));

        $token = $provider->getAccessToken('authorization_code', ['code' => 'code-1']);
        $request = $this->lastRequest();

        parse_str((string) $request->getBody(), $body);

        self::assertSame('access-1', $token->getToken());
        self::assertSame('refresh-1', $token->getRefreshToken());
        self::assertSame(
            'https://login.twinfield.com/auth/authentication/connect/token',
            (string) $request->getUri(),
        );
        self::assertSame('code-1', $body['code']);
        self::assertSame('secret', $body['client_secret']);
    }

    public function testThrowsWhenTwinfieldRejectsTheGrant(): void
    {
        $provider = $this->provider(self::json(['error' => 'invalid_grant'], 400));

        $this->expectException(IdentityProviderException::class);

        $provider->getAccessToken('authorization_code', ['code' => 'expired']);
    }

    public function testValidatesAnAccessToken(): void
    {
        $provider = $this->provider(self::json([
            'exp' => 1794165191,
            'scope' => ['openid', 'twf.organisationUser', 'twf.organisation'],
            'twf.organisationUserCode' => 'JDOE',
            'twf.organisationId' => '0b6a5f6e-1111-2222-3333-444455556666',
            'twf.organisationCode' => 'EXAMPLE',
            'twf.clusterUrl' => 'https://api.accounting.twinfield.com',
        ]));

        $validation = $provider->getAccessTokenValidation(new AccessToken(['access_token' => 'access 1']));
        $request = $this->lastRequest();

        self::assertSame('EXAMPLE', $validation->organisationCode);
        self::assertSame('https://api.accounting.twinfield.com', $validation->clusterUrl);
        self::assertSame(
            'https://login.twinfield.com/auth/authentication/connect/accesstokenvalidation?token=access%201',
            (string) $request->getUri(),
        );
        self::assertSame('Bearer access 1', $request->getHeaderLine('Authorization'));
    }

    public function testThrowsWhenTheValidationIsNotJson(): void
    {
        $provider = $this->provider(new Response(200, ['Content-Type' => 'text/html'], '<html></html>'));

        $this->expectException(UnexpectedResponseException::class);

        $provider->getAccessTokenValidation(new AccessToken(['access_token' => 'access-1']));
    }

    public function testThrowsOnAnEmptyAccessToken(): void
    {
        $this->expectException(IdentityProviderException::class);

        $this->provider()->getAccessTokenValidation(new AccessToken(['access_token' => ' ']));
    }

    public function testReadsTheResourceOwner(): void
    {
        $provider = $this->provider(self::json([
            'sub' => 'user-guid',
            'twf.organisationUserCode' => 'JDOE',
            'twf.organisationId' => '0b6a5f6e-1111-2222-3333-444455556666',
        ]));

        $owner = $provider->getResourceOwner(new AccessToken(['access_token' => 'access-1']));

        self::assertInstanceOf(TwinfieldResourceOwner::class, $owner);
        self::assertSame('user-guid', $owner->getId());
        self::assertSame('JDOE', $owner->organisationUserCode);
        self::assertSame(
            'https://login.twinfield.com/auth/authentication/connect/userinfo',
            (string) $this->lastRequest()->getUri(),
        );
    }

    private function provider(Response ...$responses): Twinfield
    {
        $this->mock = new MockHandler($responses);

        return new Twinfield(
            ['clientId' => 'client', 'clientSecret' => 'secret', 'redirectUri' => 'https://example.com/callback'],
            ['httpClient' => new Client(['handler' => HandlerStack::create($this->mock)])],
        );
    }

    private function lastRequest(): RequestInterface
    {
        return $this->mock->getLastRequest() ?? self::fail('No request was sent.');
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function json(array $data, int $status = 200): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], (string) json_encode($data));
    }
}
