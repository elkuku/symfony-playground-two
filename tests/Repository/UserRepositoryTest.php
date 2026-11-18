<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class UserRepositoryTest extends KernelTestCase
{
    private UserRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        /** @var UserRepository $repository */
        $repository = self::getContainer()->get(UserRepository::class);
        $this->repository = $repository;
    }

    public function testFindUsersReturnsOnlyRoleUser(): void
    {
        $users = $this->repository->findUsers();

        self::assertNotEmpty($users);
        foreach ($users as $user) {
            self::assertSame(UserRole::USER, $user->getRole());
        }
    }

    public function testFindUsersExcludesAdmins(): void
    {
        $users = $this->repository->findUsers();

        $identifiers = \array_map(static fn (User $u): string => $u->getIdentifier(), $users);
        self::assertNotContains('admin', $identifiers);
    }

    public function testFindUsersOrderedById(): void
    {
        $users = $this->repository->findUsers();

        $ids = \array_map(static fn (User $u): ?int => $u->id, $users);
        $sorted = $ids;
        \sort($sorted);

        self::assertSame($sorted, $ids);
    }

    public function testGetAvailableIdentifierReturnsFreeIdentifierUnchanged(): void
    {
        self::assertSame('nobody-has-this', $this->repository->getAvailableIdentifier('nobody-has-this'));
    }

    public function testGetAvailableIdentifierAddsSuffixWhenTaken(): void
    {
        // "admin" is created by the fixtures.
        self::assertSame('admin-2', $this->repository->getAvailableIdentifier('admin'));
    }
}
