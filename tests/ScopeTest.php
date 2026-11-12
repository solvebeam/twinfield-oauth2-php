<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\OAuth2\Tests;

use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\OAuth2\Scope;

final class ScopeTest extends TestCase
{
    public function testListsTheIdentityScopes(): void
    {
        self::assertSame(
            ['openid', 'twf.user', 'twf.organisation', 'twf.organisationUser', 'offline_access'],
            Scope::values(...Scope::identity()),
        );
    }

    public function testListsTheRestScopes(): void
    {
        self::assertContains(Scope::ReadFixedAssets, Scope::rest());
        self::assertNotContains(Scope::OpenId, Scope::rest());
        self::assertCount(14, Scope::rest());
    }

    public function testListsEachValueOnce(): void
    {
        self::assertSame(
            ['readVat', 'openid'],
            Scope::values(Scope::ReadVat, Scope::OpenId, Scope::ReadVat),
        );
    }
}
