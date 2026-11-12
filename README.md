# Twinfield Provider for OAuth 2.0 Client

This package provides Twinfield OAuth 2.0 support for the PHP League's [OAuth 2.0 Client](https://github.com/thephpleague/oauth2-client).

Twinfield uses OpenID Connect with the authorization code flow. You need a client ID and secret from Twinfield, see the [Twinfield documentation](https://developers.twinfield.com/documentation/api/authentication/openid-connect-auth).

For the Twinfield API itself, use the [Twinfield PHP SDK](https://php.twinfield.solvebeam.com), which builds on this package.

## Installation

```sh
composer require solvebeam/oauth2-twinfield
```

Requires PHP 8.3 or higher.

## Usage

### Authorization code flow

```php
<?php

use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use SolveBeam\Twinfield\OAuth2\Provider\Twinfield;

session_start();

$provider = new Twinfield([
    'clientId' => 'your-client-id',
    'clientSecret' => 'your-client-secret',
    'redirectUri' => 'https://example.com/callback',
]);

if (!isset($_GET['code'])) {
    // 1. Send the user to the Twinfield login.
    $url = $provider->getAuthorizationUrl([
        'nonce' => bin2hex(random_bytes(16)),
    ]);

    $_SESSION['twinfield_state'] = $provider->getState();

    header('Location: ' . $url);
    exit;
}

// 2. Twinfield redirects back with a code and the state.
$state = $_SESSION['twinfield_state'] ?? '';
unset($_SESSION['twinfield_state']);

if ($state === '' || !hash_equals($state, (string) ($_GET['state'] ?? ''))) {
    exit('Invalid state.');
}

try {
    // 3. Exchange the code for tokens.
    $token = $provider->getAccessToken('authorization_code', ['code' => $_GET['code']]);
} catch (IdentityProviderException $exception) {
    exit('Token request failed: ' . $exception->getMessage());
}

// 4. Ask Twinfield who the token belongs to and where the API is.
$validation = $provider->getAccessTokenValidation($token);

echo $validation->organisationCode, PHP_EOL;     // EXAMPLE
echo $validation->organisationUserCode, PHP_EOL; // JDOE
echo $validation->clusterUrl, PHP_EOL;           // https://api.accounting.twinfield.com

// Store the refresh token to get new access tokens later.
$refreshToken = $token->getRefreshToken();
```

### Refreshing a token

An access token is valid for about an hour. Use the refresh token to get a new one.

```php
$token = $provider->getAccessToken('refresh_token', ['refresh_token' => $refreshToken]);

// Twinfield may return a new refresh token; keep the old one when it does not.
$refreshToken = $token->getRefreshToken() ?? $refreshToken;
```

### Access token validation

`getAccessTokenValidation()` calls Twinfield's access token validation endpoint. It returns an `AccessTokenValidation` with:

| Property | Claim | Description |
|----------|-------|-------------|
| `exp` | `exp` | Expiry time of the access token, as a Unix timestamp. |
| `organisationId` | `twf.organisationId` | GUID of the organisation. |
| `organisationCode` | `twf.organisationCode` | Code of the organisation. |
| `organisationUserCode` | `twf.organisationUserCode` | Code of the user in the organisation. |
| `clusterUrl` | `twf.clusterUrl` | Base URL of the cluster to send API calls to. |
| `scopes` | `scope` | The scopes of the token. `hasScope()` checks for one. |

It throws an `UnexpectedResponseException` when a claim is missing. That happens when the scopes `twf.organisationUser` and `twf.organisation` were not requested at login.

### Resource owner

```php
$owner = $provider->getResourceOwner($token);

echo $owner->getId(), PHP_EOL;              // sub
echo $owner->organisationUserCode, PHP_EOL;
echo $owner->organisationId, PHP_EOL;
```

## Scopes

The `Scope` enum has all scopes of Twinfield.

`Scope::identity()` gives the scopes that identify the user and keep the session. Every Twinfield API needs them, so the provider requests them when you pass no `scope`:

| Scope | Case | Description |
|-------|------|-------------|
| `openid` | `Scope::OpenId` | Standard OpenID Connect scope. |
| `twf.user` | `Scope::User` | Twinfield ID and user code. |
| `twf.organisation` | `Scope::Organisation` | Organisation code, ID and cluster URL. |
| `twf.organisationUser` | `Scope::OrganisationUser` | User code in the organisation. Mandatory to log in. |
| `offline_access` | `Scope::OfflineAccess` | Needed to get a refresh token. |

`Scope::rest()` gives the scopes of the Twinfield REST API, such as `readVat` and `writeArticles`. Each REST operation needs one of them on top of the identity scopes, and your client must be allowed to request it. A `scope` you pass replaces the default, so include the identity scopes:

```php
use SolveBeam\Twinfield\OAuth2\Scope;

$url = $provider->getAuthorizationUrl([
    'scope' => Scope::values(Scope::ReadVat, ...Scope::identity()),
]);
```

## Other providers of Twinfield tokens

`AbstractTwinfieldProvider` has everything that does not depend on where the user logs in: the access token validation, the resource owner, the scopes and the errors. Extend it for a service that issues Twinfield access tokens in another way. [Twinfield Connect](https://github.com/solvebeam/twinfield-connect-php) does this for logging in without a Twinfield client ID of your own.

## Testing

```sh
composer test
composer phpcs
composer phpstan
```

## License

The MIT License (MIT). See [LICENSE](LICENSE).
