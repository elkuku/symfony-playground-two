<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Enum\UserRole;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ObjectManager;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class UserControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    private ObjectManager $manager;

    /**
     * @var EntityRepository<User>
     */
    private EntityRepository $userRepository;

    private string $path = '/user/';

    protected function setUp(): void
    {
        $this->client = self::createClient();

        /**
         * @var Registry $doctrine
         */
        $doctrine = self::getContainer()->get('doctrine');
        $this->manager = $doctrine->getManager();

        /**
         * @var EntityRepository<User> $repository
         */
        $repository = $this->manager->getRepository(User::class);
        $this->userRepository = $repository;

        /**
         * @var User $user
         */
        $user = $this->userRepository->findOneBy(['identifier' => 'admin']);
        $this->client->loginUser($user);
        $this->client->followRedirects();
    }

    public function testIndex(): void
    {
        $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('User index');
    }

    public function testNew(): void
    {
        $initialCount = $this->userRepository->count([]);
        $this->client->request('GET', \sprintf('%snew', $this->path));

        self::assertResponseStatusCodeSame(200);

        $this->client->submitForm('Save', [
            'user[identifier]' => 'Testing',
            'user[role]' => 'ROLE_USER',
        ]);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('User index');

        self::assertSame($initialCount + 1, $this->userRepository->count([]));
    }

    public function testShow(): void
    {
        $fixture = new User();
        $fixture->setIdentifier('My Identifier');
        $fixture->setRole(UserRole::from('ROLE_USER'));
        $fixture->setParams(['My' => 'Param']);
        $fixture->setGoogleId('MyGoogleId');
        $fixture->setGitHubId(12345);
        $fixture->setGitLabId(54321);

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', \sprintf('%s%s', $this->path, $fixture->id));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('User');

        // Use assertions to check that the properties are properly displayed.
    }

    public function testEdit(): void
    {
        $fixture = new User();
        $fixture->setIdentifier('Value');
        $fixture->setRole(UserRole::from('ROLE_USER'));

        $this->manager->persist($fixture);
        $this->manager->flush();

        $id = $fixture->id;

        $this->client->request('GET', \sprintf('%s%s/edit', $this->path, $fixture->id));
        self::assertPageTitleContains('Edit User');
        $this->client->submitForm('Update', [
            'user[identifier]' => 'Something New',
            'user[role]' => 'ROLE_ADMIN',
        ]);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('User');

        /**
         * @var User $fixture
         */
        $fixture = $this->userRepository->findOneBy(['id' => $id]);

        self::assertSame('Something New', $fixture->getIdentifier());
        self::assertContains('ROLE_ADMIN', $fixture->getRoles());
    }

    public function testRemove(): void
    {
        $initialCount = $this->userRepository->count([]);

        $fixture = new User();
        $fixture->setIdentifier('Value');
        $fixture->setRole(UserRole::from('ROLE_USER'));
        $fixture->setParams(['My' => 'Param']);
        $fixture->setGoogleId('MyGoogleId');
        $fixture->setGitHubId(12345);
        $fixture->setGitLabId(54321);

        $this->manager->persist($fixture);
        $this->manager->flush();

        self::assertSame($initialCount + 1, $this->userRepository->count([]));

        $this->client->request('GET', \sprintf('%s%s', $this->path, $fixture->id));
        $this->client->submitForm('Delete');

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('User');

        self::assertSame($initialCount, $this->userRepository->count([]));
    }

    public function testOwnAccountHasNoDeleteButton(): void
    {
        $admin = $this->userRepository->findOneBy(['identifier' => 'admin']);
        self::assertNotNull($admin);

        $this->client->request('GET', \sprintf('%s%s', $this->path, $admin->id));

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('form[action$="/user/'.$admin->id.'"] button');
    }

    public function testCannotDeleteOwnAccount(): void
    {
        $admin = $this->userRepository->findOneBy(['identifier' => 'admin']);
        self::assertNotNull($admin);

        $this->client->request('POST', \sprintf('%s%s', $this->path, $admin->id), ['_token' => 'irrelevant']);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.alert-danger[role="alert"]', 'You cannot delete your own account.');
        self::assertNotNull($this->userRepository->findOneBy(['identifier' => 'admin']));
    }

    public function testOwnRoleFieldIsDisabled(): void
    {
        $admin = $this->userRepository->findOneBy(['identifier' => 'admin']);
        self::assertNotNull($admin);

        $this->client->request('GET', \sprintf('%s%s/edit', $this->path, $admin->id));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('select[name="user[role]"][disabled]');
    }

    public function testCannotChangeOwnRole(): void
    {
        $admin = $this->userRepository->findOneBy(['identifier' => 'admin']);
        self::assertNotNull($admin);

        $crawler = $this->client->request('GET', \sprintf('%s%s/edit', $this->path, $admin->id));
        $token = $crawler->filter('input[name="user[_token]"]')->attr('value');

        // Post the role anyway, as a hand-crafted request would.
        $this->client->request('POST', \sprintf('%s%s/edit', $this->path, $admin->id), [
            'user' => ['identifier' => 'admin', 'role' => 'ROLE_USER', '_token' => $token],
        ]);

        self::assertResponseIsSuccessful();

        $this->manager->clear();
        $admin = $this->userRepository->findOneBy(['identifier' => 'admin']);
        self::assertNotNull($admin);
        self::assertSame(UserRole::ADMIN, $admin->getRole());
    }
}
