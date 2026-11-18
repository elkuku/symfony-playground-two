<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testGetUserIdentifierThrowsWhenEmpty(): void
    {
        $this->expectException(\LogicException::class);
        new User()->getUserIdentifier();
    }

    public function testGetPassword(): void
    {
        self::assertNull(new User()->getPassword());
    }

    public function testGitLabId(): void
    {
        $user = new User();
        self::assertNull($user->getGitLabId());

        $user->setGitLabId(42);
        self::assertSame(42, $user->getGitLabId());

        $user->setGitLabId(null);
        self::assertNull($user->getGitLabId());
    }

    public function testSerialize(): void
    {
        $user = new User()->setIdentifier('foo@example.com');

        $data = $user->__serialize();

        self::assertSame('foo@example.com', $data['identifier']);

        $copy = new User();
        $copy->__unserialize($data);

        self::assertSame('foo@example.com', $copy->getIdentifier());
    }

    public function testNewUserHasNoId(): void
    {
        self::assertNull(new User()->id);
    }

    public function testIdCannotBeChangedFromOutside(): void
    {
        $this->expectException(\Error::class);

        $user = new User();
        // @phpstan-ignore assign.propertyPrivateSet
        $user->id = 42;
    }
}
