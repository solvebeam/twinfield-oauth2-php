# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.1] - 2026-11-12

### Fixed

- `AccessTokenValidation::fromArray()` accepts the expiry time as Twinfield sends it, a string of digits (`"exp": "1794165191"`), instead of throwing `UnexpectedResponseException`.

## [0.1.0] - 2026-11-12

### Added

- `Twinfield` provider for `league/oauth2-client`, split off from the `solvebeam/twinfield` SDK.
- `AbstractTwinfieldProvider` as the base for other providers of Twinfield access tokens.
- `AccessTokenValidation` with `getAccessTokenValidation()`, `TwinfieldResourceOwner` and the `Scope` enum.
- The identity scopes (`Scope::identity()`) are requested when no `scope` is passed.
- HTTP errors of Twinfield are thrown as `IdentityProviderException` with Twinfield's error as message and the response as body.

[unreleased]: https://github.com/solvebeam/twinfield-oauth2-php/compare/v0.1.1...HEAD
[0.1.1]: https://github.com/solvebeam/twinfield-oauth2-php/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/solvebeam/twinfield-oauth2-php/releases/tag/v0.1.0
