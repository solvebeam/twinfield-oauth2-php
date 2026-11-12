<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\OAuth2\Provider;

use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use SolveBeam\Twinfield\OAuth2\UnexpectedResponseException;

/**
 * The user of the access token, from the user info endpoint.
 *
 * The claims come from the scopes `openid`, `twf.organisationUser` and `twf.organisation`.
 */
final readonly class TwinfieldResourceOwner implements ResourceOwnerInterface
{
    /** Subject of the token: the ID of the user at Twinfield. */
    public string $sub;

    /** Code of the user in the organisation (`twf.organisationUserCode`), e.g. `JDOE`. */
    public string $organisationUserCode;

    /** GUID of the organisation (`twf.organisationId`). */
    public string $organisationId;

    /** @var array<string, mixed> */
    private array $response;

    /**
     * @param array<string, mixed> $response
     *
     * @throws UnexpectedResponseException When a claim is missing, e.g. because a scope was not requested.
     */
    public function __construct(array $response)
    {
        $this->sub = self::claim($response, 'sub');
        $this->organisationUserCode = self::claim($response, 'twf.organisationUserCode');
        $this->organisationId = self::claim($response, 'twf.organisationId');
        $this->response = $response;
    }

    public function getId(): string
    {
        return $this->sub;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->response;
    }

    /**
     * @param array<string, mixed> $response
     */
    private static function claim(array $response, string $name): string
    {
        $value = $response[$name] ?? null;

        if (!is_string($value) || $value === '') {
            throw new UnexpectedResponseException(sprintf(
                'User info has no "%s"; request the scopes twf.organisationUser and twf.organisation at login.',
                $name,
            ));
        }

        return $value;
    }
}
